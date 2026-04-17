<?php

namespace Tests\Feature\TenantPush;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\Plan;
use App\Models\TenantOnesignalPushIdentity;
use App\Models\TenantPushEventPreference;
use App\Models\User;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationPayloadDto;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Services\CurrentTenantManager;
use App\TenantPush\TenantPushAccessDenialCode;
use App\TenantPush\TenantPushDiagnosticCode;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushOnesignalClient;
use App\TenantPush\TenantPushOverride;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushNotificationBindingSync;
use App\TenantPush\TenantPushSettingsView;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

/**
 * Mirrors plan "Acceptance minimum" (§11 / Acceptance block).
 */
class TenantPushAcceptanceScenariosTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_1_tenant_without_entitlement_cannot_edit_settings(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $tenant = $this->createTenantWithActiveDomain('acc1', ['plan_id' => $plan->id]);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = false;
        $settings->push_override = TenantPushOverride::InheritPlan->value;
        $settings->self_serve_allowed = true;
        $settings->save();

        $g = $gate->evaluate($tenant);
        $this->assertFalse($g->isFeatureEntitled());
        $this->assertFalse($g->canEditSettings);
    }

    public function test_2_platform_force_enable_entitles_but_does_not_allow_edit_when_self_serve_off(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $tenant = $this->createTenantWithActiveDomain('acc2', ['plan_id' => $plan->id]);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = false;
        $settings->push_override = TenantPushOverride::ForceEnable->value;
        $settings->self_serve_allowed = false;
        $settings->save();

        $g = $gate->evaluate($tenant);
        $this->assertTrue($g->isFeatureEntitled());
        $this->assertFalse($g->canEditSettings);
        $this->assertSame(TenantPushAccessDenialCode::None, $g->entitlementDenialCode());
        $this->assertSame(TenantPushAccessDenialCode::SelfServeForbidden, $g->editDenialCode());
    }

    public function test_3_verified_provider_without_subscriptions_is_not_event_ready(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('acc3', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = true;
        $settings->is_push_enabled = true;
        $settings->provider_status = TenantPushProviderStatus::Verified->value;
        $settings->save();

        TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => true,
                'delivery_mode' => 'immediate',
                'recipient_scope' => 'owner_only',
            ],
        );

        $view = TenantPushSettingsView::make($tenant, $gate, app(\App\TenantPush\TenantPushCrmRequestRecipientResolver::class));

        $this->assertFalse($view->readyForEventDelivery);
        $this->assertStringContainsString('Провайдер проверен', $view->readinessHint());
    }

    public function test_4_test_push_uses_canonical_external_user_id_and_http(): void
    {
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => 'msg-accept'], 200),
        ]);

        $tenant = $this->createNotificationTenant();
        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->push_override = TenantPushOverride::ForceEnable->value;
        $settings->is_push_enabled = true;
        $settings->onesignal_app_id = 'app-x';
        $settings->onesignal_app_api_key_encrypted = 'secret-key-plain';
        $settings->save();

        $user = User::factory()->create();
        $plainKey = $settings->fresh()->onesignal_app_api_key_encrypted;
        $result = app(TenantPushOnesignalClient::class)->sendTestToExternalUserIds(
            'app-x',
            (string) $plainKey,
            ['t'.$tenant->id.'_u'.$user->id],
            't',
            'b',
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(TenantPushDiagnosticCode::Ok, $result['code']);

        Http::assertSent(function ($request) use ($tenant, $user): bool {
            $data = json_decode($request->body(), true);

            return is_array($data)
                && in_array('t'.$tenant->id.'_u'.$user->id, $data['include_external_user_ids'] ?? [], true);
        });
    }

    public function test_5_crm_request_created_flow_creates_web_push_onesignal_delivery(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('acc5crm', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = true;
        $settings->is_push_enabled = true;
        $settings->provider_status = TenantPushProviderStatus::Verified->value;
        $settings->save();

        TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => true,
                'delivery_mode' => 'immediate',
                'recipient_scope' => 'owner_only',
            ],
        );

        TenantOnesignalPushIdentity::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'external_user_id' => 't'.$tenant->id.'_u'.$user->id,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        app(TenantPushNotificationBindingSync::class)->syncCrmRequestCreated($tenant);

        $submission = new PublicInboundSubmission(
            requestType: 'tenant_booking',
            name: 'Renter',
            phone: '+79993332211',
            email: 'r-acc5@example.test',
            message: 'Hi',
            source: 'test',
            channel: 'web',
        );

        app(CreateCrmRequestFromPublicForm::class)->handle(
            PublicInboundContext::tenant($tenant->id),
            $submission,
        );

        $this->assertSame(
            1,
            NotificationDelivery::query()
                ->where('tenant_id', $tenant->id)
                ->where('channel_type', NotificationChannelType::WebPushOnesignal->value)
                ->count(),
        );
    }

    public function test_6_missing_subscriptions_skips_delivery_with_normalized_code(): void
    {
        $tenant = $this->createNotificationTenant();
        $user = User::factory()->create(['status' => 'active']);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->push_override = TenantPushOverride::ForceEnable->value;
        $settings->is_push_enabled = true;
        $settings->onesignal_app_id = 'app-id';
        $settings->onesignal_app_api_key_encrypted = 'k';
        $settings->save();

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'name' => 'OneSignal',
            'type' => NotificationChannelType::WebPushOnesignal->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => false,
            'config_json' => [],
        ]);

        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('T', 'B', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::WebPushOnesignal->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Skipped->value, $delivery->status);
        $this->assertStringContainsString('no_active_subscriptions', json_encode($delivery->response_json));
    }
}
