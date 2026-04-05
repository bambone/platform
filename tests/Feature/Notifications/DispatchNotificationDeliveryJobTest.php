<?php

namespace Tests\Feature\Notifications;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryAttempt;
use App\Models\NotificationEvent;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\Services\CurrentTenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class DispatchNotificationDeliveryJobTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_noop_when_delivery_already_processing(): void
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
            'status' => NotificationDeliveryStatus::Processing->value,
            'queued_at' => now(),
        ]);

        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        $this->assertSame(0, NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count());
    }

    public function test_noop_when_delivery_already_delivered(): void
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
            'status' => NotificationDeliveryStatus::Delivered->value,
            'queued_at' => now(),
        ]);

        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        $this->assertSame(0, NotificationDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count());
    }
}
