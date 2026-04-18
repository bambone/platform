<?php

declare(strict_types=1);

namespace Tests\Feature\TenantSiteSetup;

use App\Filament\Tenant\Pages\TenantSiteSetupBookingNotificationsPage;
use App\Models\TenantSetting;
use App\Models\User;
use App\Tenant\CurrentTenant;
use App\TenantSiteSetup\SetupProductSignalsRepository;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class BookingNotificationsBriefProductSignalsLivewireTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_save_draft_writes_calendar_signals_and_questionnaire_has_no_product_cal_keys(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $this->assertTrue(TenantSiteSetupFeature::enabled());

        $tenant = $this->createTenantWithActiveDomain('brief_ps_live', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        Livewire::test(TenantSiteSetupBookingNotificationsPage::class)
            ->set('data.product_cal_uses_external', '1')
            ->set('data.product_cal_providers', ['google', 'yandex', 'other'])
            ->set('data.product_cal_other_text', 'Custom X')
            ->set('data.product_cal_notes', 'Календарный контекст из теста')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $merged = app(SetupProductSignalsRepository::class)->getMerged((int) $tenant->id);
        $this->assertTrue($merged['calendar_signals']['uses_external_calendars']);
        $this->assertSame(['google', 'yandex', 'other'], $merged['calendar_signals']['providers']);
        $this->assertSame('Custom X', $merged['calendar_signals']['other_provider_text']);
        $this->assertSame('Календарный контекст из теста', $merged['calendar_signals']['notes']);

        $row = TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('group', 'setup')
            ->where('key', 'booking_notifications_questionnaire')
            ->first();
        $this->assertNotNull($row);
        $stored = json_decode((string) $row->value, true);
        $this->assertIsArray($stored);
        foreach (array_keys($stored) as $key) {
            $this->assertFalse(
                is_string($key) && str_starts_with($key, 'product_cal_'),
                'Черновик анкеты не должен содержать ключей product_cal_*: '.$key
            );
        }
    }

    public function test_save_draft_external_calendars_no_clears_stored_details(): void
    {
        config(['features.tenant_site_setup_framework' => true]);

        $tenant = $this->createTenantWithActiveDomain('brief_ps_no', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        Livewire::test(TenantSiteSetupBookingNotificationsPage::class)
            ->set('data.product_cal_uses_external', '0')
            ->set('data.product_cal_providers', ['google'])
            ->set('data.product_cal_other_text', 'orphan')
            ->set('data.product_cal_notes', 'should not persist')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $merged = app(SetupProductSignalsRepository::class)->getMerged((int) $tenant->id);
        $this->assertFalse($merged['calendar_signals']['uses_external_calendars']);
        $this->assertSame([], $merged['calendar_signals']['providers']);
        $this->assertSame('', $merged['calendar_signals']['other_provider_text']);
        $this->assertSame('', $merged['calendar_signals']['notes']);
    }
}
