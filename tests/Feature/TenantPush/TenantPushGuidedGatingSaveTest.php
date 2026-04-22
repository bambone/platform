<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPush;

use App\Filament\Tenant\Pages\TenantPushPwaSettingsPage;
use App\Models\Plan;
use App\Models\TenantPushEventPreference;
use App\Models\User;
use App\Tenant\CurrentTenant;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushRecipientScope;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class TenantPushGuidedGatingSaveTest extends TestCase
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

    public function test_save_rejects_crm_push_when_baseline_incomplete_with_expected_reason_in_notification(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create(['status' => 'active']);
        $tenant = $this->createTenantWithActiveDomain('gating1', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $user->assignRole('tenant_owner');

        $host = strtolower((string) $tenant->domains()->firstOrFail()->host);
        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->fill([
            'canonical_host' => $host,
            'canonical_origin' => 'https://'.$host,
            'onesignal_app_id' => 'ok-app',
            'is_push_enabled' => true,
            'commercial_service_active' => true,
        ]);
        $settings->onesignal_app_api_key_encrypted = 'k';
        $settings->provider_status = TenantPushProviderStatus::Invalid->value;
        $settings->save();

        TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => false,
                'delivery_mode' => 'immediate',
                'recipient_scope' => TenantPushRecipientScope::OwnerOnly->value,
            ],
        );

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        Livewire::test(TenantPushPwaSettingsPage::class)
            ->set('data.canonical_host', $host)
            ->set('data.onesignal_app_id', 'ok-app')
            ->set('data.onesignal_app_api_key', '')
            ->set('data.has_api_key', true)
            ->set('data.clear_onesignal_api_key', false)
            ->set('data.is_push_enabled', true)
            ->set('data.crm_push_enabled', true)
            ->set('data.recipient_scope', TenantPushRecipientScope::OwnerOnly->value)
            ->set('data.selected_user_ids', [])
            ->set('data.is_pwa_enabled', false)
            ->call('save');

        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();
        $this->assertNotNull($pref);
        $this->assertFalse($pref->is_enabled);
    }
}
