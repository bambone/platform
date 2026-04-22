<?php

declare(strict_types=1);

namespace Tests\Unit\TenantPush;

use App\Models\Plan;
use App\Models\TenantPushEventPreference;
use App\Models\User;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushGuidedSetupReason;
use App\TenantPush\TenantPushGuidedSetupState;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushRecipientScope;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class TenantPushGuidedSetupStateTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_crm_wants_on_but_provider_not_verified_yields_onesignal_not_verified_code(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('gst1', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $host = strtolower((string) $tenant->domains()->firstOrFail()->host);
        $settings->fill([
            'canonical_host' => $host,
            'canonical_origin' => 'https://'.$host,
            'onesignal_app_id' => 'app-id',
            'is_push_enabled' => true,
            'commercial_service_active' => true,
        ]);
        $settings->onesignal_app_api_key_encrypted = 'secret';
        $settings->provider_status = TenantPushProviderStatus::Invalid->value;
        $settings->save();
        $settings->refresh();

        $pref = TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => false,
                'delivery_mode' => 'immediate',
                'recipient_scope' => TenantPushRecipientScope::OwnerOnly->value,
            ],
        );

        $g = $gate->evaluate($tenant);
        $form = [
            'canonical_host' => $host,
            'onesignal_app_id' => 'app-id',
            'onesignal_app_api_key' => '',
            'clear_onesignal_api_key' => false,
            'is_push_enabled' => true,
            'crm_push_enabled' => true,
            'recipient_scope' => TenantPushRecipientScope::OwnerOnly->value,
            'selected_user_ids' => [],
        ];
        $state = TenantPushGuidedSetupState::make($tenant, $g, $settings, $pref, $form);

        $this->assertFalse($state->canEnableCrmPush);
        $this->assertSame(TenantPushGuidedSetupReason::OneSignalNotVerified, $state->primaryReason);
    }

    public function test_can_verify_persisted_only_when_api_key_in_database(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('gst2', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $host = strtolower((string) $tenant->domains()->firstOrFail()->host);
        $settings->fill([
            'canonical_host' => $host,
            'onesignal_app_id' => 'x',
            'commercial_service_active' => true,
        ]);
        $settings->onesignal_app_api_key_encrypted = null;
        $settings->save();
        $settings->refresh();
        $g = $gate->evaluate($tenant);

        $form = [
            'canonical_host' => $host,
            'onesignal_app_id' => 'x',
            'onesignal_app_api_key' => 'new-not-saved',
            'clear_onesignal_api_key' => false,
            'is_push_enabled' => false,
            'crm_push_enabled' => false,
            'recipient_scope' => TenantPushRecipientScope::OwnerOnly->value,
            'selected_user_ids' => [],
        ];
        $state = TenantPushGuidedSetupState::make($tenant, $g, $settings, null, $form);

        $this->assertFalse($state->canVerifyOnesignal);
        $this->assertStringContainsString('Сохраните', $state->verifyActionDisabledMessage);
    }
}
