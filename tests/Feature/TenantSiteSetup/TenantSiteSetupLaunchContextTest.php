<?php

declare(strict_types=1);

namespace Tests\Feature\TenantSiteSetup;

use App\Models\User;
use App\Tenant\CurrentTenant;
use App\TenantSiteSetup\SetupLaunchContextPresenter;
use App\TenantSiteSetup\SetupLaunchUiTrackState;
use App\TenantSiteSetup\SetupProfileRepository;
use App\TenantSiteSetup\TenantOnboardingBranchId;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantSiteSetupLaunchContextTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_presenter_marks_scheduling_suppressed_when_module_disabled(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $this->assertTrue(TenantSiteSetupFeature::enabled());

        $tenant = $this->createTenantWithActiveDomain('ts_sched_off', ['theme_key' => 'expert_auto']);
        $tenant->scheduling_module_enabled = false;
        $tenant->save();

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $ctx = app(SetupLaunchContextPresenter::class)->present($tenant, $user);
        $sched = null;
        foreach ($ctx->tracks as $row) {
            if ($row->key === 'scheduling') {
                $sched = $row;
                break;
            }
        }
        $this->assertNotNull($sched);
        $this->assertSame(SetupLaunchUiTrackState::Suppressed, $sched->state);
        $this->assertSame('scheduling_module_disabled', $sched->reasonCode);
    }

    public function test_presenter_suppresses_scheduling_track_when_onboarding_branch_is_crm_only(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $this->assertTrue(TenantSiteSetupFeature::enabled());

        $tenant = $this->createTenantWithActiveDomain('ts_branch_crm', ['theme_key' => 'expert_auto']);
        $tenant->scheduling_module_enabled = true;
        $tenant->save();

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $profiles = app(SetupProfileRepository::class);
        $profiles->save($tenant->id, array_merge($profiles->getMerged($tenant->id), [
            'desired_branch' => TenantOnboardingBranchId::CrmOnly->value,
        ]));

        $ctx = app(SetupLaunchContextPresenter::class)->present($tenant, $user);
        $sched = null;
        foreach ($ctx->tracks as $row) {
            if ($row->key === 'scheduling') {
                $sched = $row;
                break;
            }
        }
        $this->assertNotNull($sched);
        $this->assertSame(SetupLaunchUiTrackState::Suppressed, $sched->state);
        $this->assertSame('onboarding_branch_crm_only', $sched->reasonCode);
    }

    public function test_overview_renders_tracks_section_and_primary_goal_booking(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_goal', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $profiles = app(SetupProfileRepository::class);
        $merged = $profiles->getMerged((int) $tenant->id);
        $merged['primary_goal'] = 'booking';
        $profiles->save((int) $tenant->id, $merged);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $this->actingAs($user);
        $this->withoutVite();

        $host = $this->tenancyHostForSlug('ts_goal');
        $response = $this->get('http://'.$host.'/admin/site-setup');
        $response->assertOk();
        $response->assertSee('Получать записи', false);
        $response->assertSee('Дорожки запуска и цель сайта', false);
    }
}
