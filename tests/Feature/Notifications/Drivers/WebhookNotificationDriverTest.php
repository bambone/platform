<?php

namespace Tests\Feature\Notifications\Drivers;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\NotificationCenter\Drivers\WebhookNotificationDriver;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationPayloadDto;
use App\Services\CurrentTenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class WebhookNotificationDriverTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_posts_json_with_hmac_when_secret_configured(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('ok', 200),
        ]);

        $tenant = $this->createNotificationTenant();

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Hook',
            'type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => [
                'url' => 'https://example.com/notify',
                'secret' => 'shared-secret',
            ],
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
            'channel_type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        Http::assertSent(function (Request $request) use ($delivery, $event): bool {
            $deliveryHeader = $request->header('X-Notification-Delivery-Id')[0] ?? null;
            $eventIdHeader = $request->header('X-Notification-Event-Id')[0] ?? null;
            $eventKeyHeader = $request->header('X-Notification-Event-Key')[0] ?? null;
            if ((string) $delivery->id !== $deliveryHeader || (string) $event->id !== $eventIdHeader || $event->event_key !== $eventKeyHeader) {
                return false;
            }

            $ts = $request->header('X-Notification-Timestamp')[0] ?? null;
            $sigLine = $request->header('X-Notification-Signature')[0] ?? '';
            if ($ts === null || ! str_starts_with($sigLine, 'sha256=')) {
                return false;
            }
            $body = (string) $request->body();
            $expected = hash_hmac('sha256', $ts.'.'.$body, 'shared-secret');
            $sig = substr($sigLine, 7);

            return hash_equals($expected, $sig);
        });

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Sent->value, $delivery->status);
        $this->assertNull($delivery->delivered_at);
    }

    public function test_http_url_in_config_fails_before_request(): void
    {
        Http::fake();

        $tenant = $this->createNotificationTenant();

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Bad',
            'type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['url' => 'http://example.com/insecure'],
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
            'channel_type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        try {
            $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));
        } catch (\InvalidArgumentException) {
            // Job rethrows for queue retry when validation fails before last attempt.
        }

        Http::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Failed->value, $delivery->status);
    }

    public function test_webhook_driver_throws_when_payload_exceeds_max_size(): void
    {
        config(['notification_center.webhook.max_payload_kb' => 1]);

        $tenant = $this->createNotificationTenant();
        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Hook',
            'type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => [
                'url' => 'https://example.com/notify',
            ],
        ]);

        $big = str_repeat('x', 5000);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto($big, $big, null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds max size');

        app(WebhookNotificationDriver::class)->send($delivery, $event, $dest);
    }

    public function test_webhook_http_error_marks_delivery_failed(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('no', 503),
        ]);

        $tenant = $this->createNotificationTenant();
        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Hook',
            'type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['url' => 'https://example.com/notify'],
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
            'channel_type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        try {
            $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));
        } catch (\Throwable) {
        }

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Failed->value, $delivery->status);
    }

    public function test_webhook_connection_failure_marks_delivery_failed(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Simulated connection failure');
        });

        $tenant = $this->createNotificationTenant();
        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Hook',
            'type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['url' => 'https://example.com/notify'],
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
            'channel_type' => NotificationChannelType::Webhook->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        try {
            $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));
        } catch (\Throwable) {
        }

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Failed->value, $delivery->status);
    }
}
