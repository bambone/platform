<?php

namespace Tests\Feature\Notifications;

use App\Models\CrmRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\NotificationRoutingContext;
use App\NotificationCenter\NotificationSeverity;
use App\NotificationCenter\Presenters\CrmRequestNotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class NotificationRouterPlannerFeatureTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_one_subscription_two_destinations_yields_two_deliveries(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $d1 = $this->createSharedInAppDestination($tenant, ['name' => 'A']);
        $d2 = $this->createSharedInAppDestination($tenant, ['name' => 'B']);

        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($sub, $d1, ['order_index' => 0]);
        $this->attachDestinationsToSubscription($sub, $d2, ['order_index' => 1]);

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
        $payload = app(CrmRequestNotificationPresenter::class)->payloadForCreated($tenant, $crm);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            class_basename(CrmRequest::class),
            (int) $crm->id,
            $payload,
        );

        $this->assertCount(2, $out['delivery_ids']);
        $this->assertSame(2, NotificationDelivery::query()->where('event_id', $out['event']?->id)->count());
    }

    public function test_disabled_subscription_matches_nothing(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->disabled()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $recorder = app(NotificationEventRecorder::class);
        $out = $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
        );

        $this->assertSame([], $out['delivery_ids']);
    }

    public function test_disabled_pivot_skips_destination(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest, ['is_enabled' => false]);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
        );

        $this->assertSame([], $out['delivery_ids']);
    }

    public function test_severity_min_filters_lower_events(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.note_added',
            'severity_min' => 'high',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.note_added',
            'CrmRequest',
            1,
            $this->samplePayload(),
        );

        $this->assertSame([], $out['delivery_ids']);
    }

    public function test_personal_subscription_ignored_when_context_has_no_recipients(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $userId = (int) User::factory()->create(['status' => 'active'])->id;
        $dest = $this->createPersonalInAppDestination($tenant, $userId);
        $sub = NotificationSubscription::factory()->forUser($userId)->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
            routingContext: new NotificationRoutingContext,
        );

        $this->assertSame([], $out['delivery_ids']);
    }

    public function test_schedule_outside_window_skips_immediate_delivery(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant(['timezone' => 'Europe/Moscow']);
        Carbon::setTestNow(Carbon::parse('2026-04-06 23:30:00', 'Europe/Moscow'));

        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'from' => '09:00',
                'to' => '22:00',
            ],
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
        );

        $this->assertSame([], $out['delivery_ids']);
    }

    public function test_two_subscriptions_same_destination_yield_single_delivery(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);

        $subA = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($subA, $dest, ['order_index' => 0]);

        $subB = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($subB, $dest, ['order_index' => 0]);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
        );

        $this->assertCount(1, $out['delivery_ids']);
        $this->assertSame(1, NotificationDelivery::query()->where('event_id', $out['event']?->id)->count());
    }

    public function test_personal_subscription_delivers_when_routing_context_includes_user(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $userId = (int) User::factory()->create(['status' => 'active'])->id;
        $dest = $this->createPersonalInAppDestination($tenant, $userId);
        $sub = NotificationSubscription::factory()->forUser($userId)->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
            routingContext: NotificationRoutingContext::forUsers([$userId]),
        );

        $this->assertCount(1, $out['delivery_ids']);
    }

    public function test_severity_min_high_allows_high_event(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.note_added',
            'severity_min' => 'high',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.note_added',
            'CrmRequest',
            1,
            $this->samplePayload(),
            severityOverride: NotificationSeverity::High,
        );

        $this->assertNotEmpty($out['delivery_ids']);
    }

    public function test_severity_min_high_allows_critical_event(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant();
        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.note_added',
            'severity_min' => 'high',
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.note_added',
            'CrmRequest',
            1,
            $this->samplePayload(),
            severityOverride: NotificationSeverity::Critical,
        );

        $this->assertNotEmpty($out['delivery_ids']);
    }

    public function test_schedule_inside_window_creates_delivery(): void
    {
        Queue::fake();

        $tenant = $this->createNotificationTenant(['timezone' => 'Europe/Moscow']);
        Carbon::setTestNow(Carbon::parse('2026-04-06 10:00:00', 'Europe/Moscow'));

        $dest = $this->createSharedInAppDestination($tenant);
        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'from' => '09:00',
                'to' => '22:00',
            ],
        ]);
        $this->attachDestinationsToSubscription($sub, $dest);

        $out = app(NotificationEventRecorder::class)->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $this->samplePayload(),
        );

        $this->assertNotEmpty($out['delivery_ids']);
    }
}
