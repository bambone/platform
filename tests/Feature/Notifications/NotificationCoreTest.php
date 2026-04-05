<?php

namespace Tests\Feature\Notifications;

use App\Models\CrmRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;
use App\Models\Tenant;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\NotificationDestinationStatus;
use App\Jobs\DispatchNotificationDeliveryJob;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\NotificationPayloadDto;
use App\NotificationCenter\Presenters\CrmRequestNotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedupe_prevents_second_event_with_same_key(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test tenant',
            'slug' => 't-'.substr(uniqid(), -10),
        ]);

        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        $r1 = $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
            dedupeKey: 'crm:1:dup',
        );
        $this->assertFalse($r1['duplicate']);
        $this->assertNotNull($r1['event']);

        $r2 = $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
            dedupeKey: 'crm:1:dup',
        );
        $this->assertTrue($r2['duplicate']);
        $this->assertNull($r2['event']);

        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_dedupe_second_record_does_not_dispatch_extra_delivery_jobs(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Test tenant',
            'slug' => 't-'.substr(uniqid(), -10),
        ]);

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'In-app',
            'type' => NotificationChannelType::InApp->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => [],
        ]);

        $sub = NotificationSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Sub',
            'event_key' => 'crm_request.created',
            'enabled' => true,
            'conditions_json' => null,
            'schedule_json' => null,
            'severity_min' => null,
            'created_by_user_id' => null,
        ]);
        $sub->destinations()->attach($dest->id, [
            'delivery_mode' => 'immediate',
            'delay_seconds' => null,
            'order_index' => 0,
            'is_enabled' => true,
        ]);

        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
            dedupeKey: 'stable',
        );
        $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
            dedupeKey: 'stable',
        );

        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, NotificationDelivery::query()->where('tenant_id', $tenant->id)->count());
        Queue::assertPushed(DispatchNotificationDeliveryJob::class, 1);
    }

    public function test_router_creates_in_app_delivery_for_subscription(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Test tenant',
            'slug' => 't-'.substr(uniqid(), -10),
        ]);

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'In-app',
            'type' => NotificationChannelType::InApp->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => [],
        ]);

        $sub = NotificationSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'New CRM',
            'event_key' => 'crm_request.created',
            'enabled' => true,
            'conditions_json' => null,
            'schedule_json' => null,
            'severity_min' => null,
            'created_by_user_id' => null,
        ]);

        $sub->destinations()->attach($dest->id, [
            'delivery_mode' => 'immediate',
            'delay_seconds' => null,
            'order_index' => 0,
            'is_enabled' => true,
        ]);

        $crm = CrmRequest::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test',
            'phone' => '+70000000000',
            'email' => null,
            'message' => 'Hello',
            'request_type' => 'tenant_booking',
            'source' => 'test',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'last_activity_at' => now(),
        ]);
        $presenter = app(CrmRequestNotificationPresenter::class);
        $payload = $presenter->payloadForCreated($tenant, $crm);

        $recorder = app(NotificationEventRecorder::class);
        $out = $recorder->record(
            $tenant->id,
            'crm_request.created',
            class_basename(CrmRequest::class),
            (int) $crm->id,
            $payload,
        );

        $this->assertFalse($out['duplicate']);
        $this->assertNotEmpty($out['delivery_ids']);

        $delivery = NotificationDelivery::query()->find($out['delivery_ids'][0]);
        $this->assertNotNull($delivery);
        $this->assertSame((string) $tenant->id, (string) $delivery->tenant_id);
        $this->assertSame(NotificationDeliveryStatus::Queued->value, $delivery->status);
    }

    public function test_payload_json_is_immutable_on_notification_event(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test tenant',
            'slug' => 't-'.substr(uniqid(), -10),
        ]);
        $event = NotificationEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('a', 'b', null, null, []))->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $event->update(['payload_json' => ['title' => 'x']]);
    }
}
