<?php

namespace Tests\Unit\TenantSiteSetup;

use App\TenantSiteSetup\SetupJourneyOrdering;
use App\TenantSiteSetup\SetupProfileRepository;
use App\TenantSiteSetup\TenantOnboardingBranchId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class SetupJourneyOrderingBookingBriefTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_booking_goal_moves_only_setup_booking_notifications_brief_earlier(): void
    {
        $tenant = $this->createTenantWithActiveDomain('j_ord', ['theme_key' => 'expert_auto']);
        app(SetupProfileRepository::class)->save($tenant->id, [
            'schema_version' => 1,
            'primary_goal' => 'booking',
            'business_focus' => '',
            'additional_notes' => '',
        ]);

        $keys = [
            'settings.site_name',
            'setup.booking_notifications_brief',
            'settings.logo',
        ];

        $ordered = app(SetupJourneyOrdering::class)->applyProfileOrdering($tenant, $keys);

        $this->assertSame(
            [
                'setup.booking_notifications_brief',
                'settings.site_name',
                'settings.logo',
            ],
            $ordered,
            'Только ключ setup.booking_notifications_brief получает штраф -55 для booking; site_name -30, logo без штрафа.'
        );
    }

    public function test_crm_only_effective_demotes_booking_brief_boost(): void
    {
        $tenant = $this->createTenantWithActiveDomain('j_ord_crm', ['theme_key' => 'expert_auto']);
        app(SetupProfileRepository::class)->save($tenant->id, [
            'schema_version' => 2,
            'primary_goal' => 'booking',
            'desired_branch' => TenantOnboardingBranchId::CrmOnly->value,
            'business_focus' => '',
            'additional_notes' => '',
        ]);

        $keys = [
            'settings.site_name',
            'setup.booking_notifications_brief',
            'settings.logo',
        ];

        $ordered = app(SetupJourneyOrdering::class)->applyProfileOrdering($tenant, $keys);

        $this->assertSame(
            [
                'settings.site_name',
                'settings.logo',
                'setup.booking_notifications_brief',
            ],
            $ordered,
            'При фактической ветке CRM без записи штраф -55 для брифа не применяется; дальше порядок по sortOrder реестра.'
        );
    }
}
