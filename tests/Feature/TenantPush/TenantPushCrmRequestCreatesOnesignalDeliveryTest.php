<?php

namespace Tests\Feature\TenantPush;

use App\Models\NotificationDelivery;
use App\Models\Plan;
use App\Models\TenantOnesignalPushIdentity;
use App\Models\TenantPushEventPreference;
use App\Models\User;
use App\NotificationCenter\NotificationChannelType;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushNotificationBindingSync;
use App\TenantPush\TenantPushProviderStatus;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPushCrmRequestCreatesOnesignalDeliveryTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_crm_request_creates_web_push_onesignal_delivery_when_configured(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('pushcrm', [
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
            email: 'r@example.test',
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

    public function test_no_web_push_onesignal_delivery_when_provider_not_verified(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('pushcrmprov', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = true;
        $settings->is_push_enabled = true;
        $settings->provider_status = TenantPushProviderStatus::Invalid->value;
        $settings->save();

        TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => true,
                'delivery_mode' => 'immediate',
                'recipient_scope' => 'owner_only',
            ],
        );

        app(TenantPushNotificationBindingSync::class)->syncCrmRequestCreated($tenant);

        $submission = new PublicInboundSubmission(
            requestType: 'tenant_booking',
            name: 'Renter',
            phone: '+79993332211',
            email: 'r-prov@example.test',
            message: 'Hi',
            source: 'test',
            channel: 'web',
        );

        app(CreateCrmRequestFromPublicForm::class)->handle(
            PublicInboundContext::tenant($tenant->id),
            $submission,
        );

        $this->assertSame(
            0,
            NotificationDelivery::query()
                ->where('tenant_id', $tenant->id)
                ->where('channel_type', NotificationChannelType::WebPushOnesignal->value)
                ->count(),
        );
    }

    public function test_no_web_push_onesignal_delivery_when_feature_not_entitled(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('pushcrm2', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->commercial_service_active = false;
        $settings->is_push_enabled = true;
        $settings->save();

        TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => true,
                'delivery_mode' => 'immediate',
                'recipient_scope' => 'owner_only',
            ],
        );

        app(TenantPushNotificationBindingSync::class)->syncCrmRequestCreated($tenant);

        $submission = new PublicInboundSubmission(
            requestType: 'tenant_booking',
            name: 'Renter',
            phone: '+79993332212',
            email: 'r2@example.test',
            message: 'Hi',
            source: 'test',
            channel: 'web',
        );

        app(CreateCrmRequestFromPublicForm::class)->handle(
            PublicInboundContext::tenant($tenant->id),
            $submission,
        );

        $this->assertSame(
            0,
            NotificationDelivery::query()
                ->where('tenant_id', $tenant->id)
                ->where('channel_type', NotificationChannelType::WebPushOnesignal->value)
                ->count(),
        );
    }
}
