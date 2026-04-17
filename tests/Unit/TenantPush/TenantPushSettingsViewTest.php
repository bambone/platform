<?php

namespace Tests\Unit\TenantPush;

use App\Models\Plan;
use App\Models\TenantOnesignalPushIdentity;
use App\Models\TenantPushEventPreference;
use App\Models\User;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushSettingsView;
use App\TenantPush\TenantPushSubscriptionAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPushSettingsViewTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_ready_for_event_requires_verified_provider_and_at_least_one_subscription(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Pro',
            'slug' => 'pro-u-'.substr(uniqid('', true), -8),
            'limits_json' => [],
            'features_json' => ['web_push_onesignal'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $tenant = $this->createTenantWithActiveDomain('tsv1', [
            'plan_id' => $plan->id,
            'owner_user_id' => $user->id,
        ]);

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

        $viewBefore = TenantPushSettingsView::make($tenant, $gate, app(\App\TenantPush\TenantPushCrmRequestRecipientResolver::class));
        $this->assertFalse($viewBefore->readyForEventDelivery);
        $this->assertSame(TenantPushSubscriptionAggregate::None, $viewBefore->subscriptionAggregate);

        TenantOnesignalPushIdentity::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'external_user_id' => 't'.$tenant->id.'_u'.$user->id,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $viewAfter = TenantPushSettingsView::make($tenant, $gate, app(\App\TenantPush\TenantPushCrmRequestRecipientResolver::class));
        $this->assertTrue($viewAfter->readyForEventDelivery);
        $this->assertSame(TenantPushSubscriptionAggregate::Active, $viewAfter->subscriptionAggregate);
    }
}
