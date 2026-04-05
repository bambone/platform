<?php

namespace Tests\Feature\Notifications;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryAttempt;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationPayloadDto;
use App\Services\CurrentTenantManager;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class NotificationRetrySemanticsTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Http::fake();
        if (class_exists(PlatformNotificationSettings::class)) {
            app(PlatformNotificationSettings::class)->setChannelEnabled('telegram', true);
        }
        parent::tearDown();
    }

    /**
     * Re-queueing a delivery for another job attempt is not modeled as a public API yet; tests update status explicitly.
     */
    public function test_second_handle_creates_new_attempt_same_event_and_delivery(): void
    {
        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => $this->samplePayload()->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => $dest->type,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        $payloadBefore = $event->fresh()->payload_json;

        app(CurrentTenantManager::class)->setTenant($tenant);
        $factory = app(NotificationChannelDriverFactory::class);

        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), $factory);

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Delivered->value, $delivery->status);
        $this->assertSame(1, NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count());

        NotificationDelivery::query()->whereKey($delivery->id)->update([
            'status' => NotificationDeliveryStatus::Queued->value,
            'delivered_at' => null,
            'sent_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ]);

        $job2 = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job2->handle(app(CurrentTenantManager::class), $factory);

        $this->assertSame(1, NotificationEvent::query()->where('id', $event->id)->count());
        $this->assertSame(1, NotificationDelivery::query()->where('id', $delivery->id)->count());
        $this->assertSame(2, NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count());
        $this->assertEquals($payloadBefore, $event->fresh()->payload_json);
    }

    public function test_second_handle_when_already_delivered_does_not_create_attempt(): void
    {
        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => $this->samplePayload()->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => $dest->type,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $factory = app(NotificationChannelDriverFactory::class);

        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), $factory);

        $job2 = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job2->handle(app(CurrentTenantManager::class), $factory);

        $this->assertSame(1, NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count());
    }

    public function test_failed_driver_then_requeued_succeeds_second_attempt(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::sequence()
                ->push(['ok' => false, 'description' => 'Bad'], 500)
                ->push(['ok' => true, 'result' => ['message_id' => 42]], 200),
        ]);

        $tenant = $this->createNotificationTenant();
        app(PlatformNotificationSettings::class)->setTelegramBotToken('tok');
        app(PlatformNotificationSettings::class)->setChannelEnabled('telegram', true);

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '99'],
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
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $factory = app(NotificationChannelDriverFactory::class);
        $tenantManager = app(CurrentTenantManager::class);

        $job1 = new DispatchNotificationDeliveryJob((int) $delivery->id);
        try {
            $job1->handle($tenantManager, $factory);
        } catch (\Throwable) {
            // Laravel queue would retry; direct handle rethrows when not last attempt.
        }

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Failed->value, $delivery->status);
        $this->assertSame(1, NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count());
        $this->assertSame('failed', NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->value('status'));

        NotificationDelivery::query()->whereKey($delivery->id)->update([
            'status' => NotificationDeliveryStatus::Queued->value,
            'failed_at' => null,
            'error_message' => null,
        ]);

        $job2 = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job2->handle($tenantManager, $factory);

        $attempts = NotificationDeliveryAttempt::query()
            ->where('delivery_id', $delivery->id)
            ->orderBy('attempt_no')
            ->get();
        $this->assertCount(2, $attempts);
        $this->assertSame('failed', $attempts[0]->status);
        $this->assertSame('succeeded', $attempts[1]->status);
        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Sent->value, $delivery->status);
    }
}
