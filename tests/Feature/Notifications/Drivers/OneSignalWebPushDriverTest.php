<?php

namespace Tests\Feature\Notifications\Drivers;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\TenantOnesignalPushIdentity;
use App\Models\TenantPushSettings;
use App\Models\User;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationPayloadDto;
use App\Services\CurrentTenantManager;
use App\TenantPush\OneSignalExternalUserId;
use App\TenantPush\TenantPushOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class OneSignalWebPushDriverTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_skips_when_no_active_identity(): void
    {
        $tenant = $this->createNotificationTenant();
        $user = User::factory()->create(['status' => 'active']);

        TenantPushSettings::query()->create([
            'tenant_id' => $tenant->id,
            'push_override' => TenantPushOverride::ForceEnable->value,
            'is_push_enabled' => true,
            'onesignal_app_id' => 'app-id',
            'onesignal_app_api_key_encrypted' => encrypt('key'),
        ]);

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

        Http::fake();

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Skipped->value, $delivery->status);
        $this->assertStringContainsString('no_active_subscriptions', (string) json_encode($delivery->response_json));
    }

    public function test_posts_to_onesignal_when_identity_exists(): void
    {
        Http::fake([
            'https://api.onesignal.com/notifications' => Http::response(['id' => 'os-msg-1'], 200),
        ]);

        $tenant = $this->createNotificationTenant();
        $user = User::factory()->create(['status' => 'active']);

        TenantPushSettings::query()->create([
            'tenant_id' => $tenant->id,
            'push_override' => TenantPushOverride::ForceEnable->value,
            'is_push_enabled' => true,
            'onesignal_app_id' => 'app-id',
            'onesignal_app_api_key_encrypted' => encrypt('key'),
        ]);

        $externalId = OneSignalExternalUserId::format((int) $tenant->id, (int) $user->id);
        TenantOnesignalPushIdentity::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'external_user_id' => $externalId,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

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
        $this->assertSame(NotificationDeliveryStatus::Sent->value, $delivery->status);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.onesignal.com/notifications'
                && str_contains($request->body(), 'include_external_user_ids');
        });
    }
}
