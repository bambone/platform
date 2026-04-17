<?php

namespace Tests\Feature\TenantPush;

use App\Models\TenantPushEventPreference;
use App\Models\TenantPushSettings;
use App\Models\User;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushOverride;
use App\TenantPush\TenantPushRecipientScope;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Коммерческий флаг не меняется из логики save страницы (platform-owned).
 */
class TenantPushTenantSaveDoesNotChangeCommercialTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_save_flow_does_not_persist_commercial_flag_from_data_array(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('commsave', [
            'owner_user_id' => $user->id,
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = true;
        $settings->push_override = TenantPushOverride::ForceDisable->value;
        $settings->self_serve_allowed = false;
        $settings->is_push_enabled = false;
        $settings->save();

        $pref = TenantPushEventPreference::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => false,
                'delivery_mode' => 'immediate',
                'recipient_scope' => TenantPushRecipientScope::OwnerOnly->value,
            ],
        );

        // Без поля commercial_service_active — как после удаления его из формы.
        $data = [
            'canonical_host' => null,
            'onesignal_app_id' => null,
            'is_push_enabled' => true,
            'is_pwa_enabled' => false,
            'crm_push_enabled' => $pref->is_enabled,
            'recipient_scope' => $pref->recipient_scope,
            'selected_user_ids' => [],
        ];

        $settings->refresh();
        $settings->fill([
            'canonical_host' => $data['canonical_host'] ? strtolower((string) $data['canonical_host']) : null,
            'canonical_origin' => null,
            'onesignal_app_id' => $data['onesignal_app_id'] ?? null,
            'is_push_enabled' => (bool) ($data['is_push_enabled'] ?? false),
            'is_pwa_enabled' => (bool) ($data['is_pwa_enabled'] ?? false),
        ]);
        $settings->save();

        $settings->refresh();
        $this->assertTrue($settings->commercial_service_active);
        $this->assertSame(TenantPushOverride::ForceDisable->value, $settings->push_override);
        $this->assertFalse($settings->self_serve_allowed);
        $this->assertTrue($settings->is_push_enabled);
    }
}
