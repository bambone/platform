<?php

namespace Tests\Unit\Notifications;

use App\Models\NotificationEvent;
use App\Models\Tenant;
use App\NotificationCenter\NotificationDedupeService;
use App\NotificationCenter\NotificationPayloadDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDedupeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_dedupe_always_inserts(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'd-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $base = [
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ];

        $svc = app(NotificationDedupeService::class);
        $r1 = $svc->tryCreateEvent($base);
        $r2 = $svc->tryCreateEvent($base);

        $this->assertFalse($r1['duplicate']);
        $this->assertFalse($r2['duplicate']);
        $this->assertSame(2, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_empty_string_dedupe_key_is_stored_as_null_and_allows_multiple_rows(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'd-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $base = [
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => '',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ];

        $svc = app(NotificationDedupeService::class);
        $r1 = $svc->tryCreateEvent($base);
        $r2 = $svc->tryCreateEvent($base);

        $this->assertFalse($r1['duplicate']);
        $this->assertFalse($r2['duplicate']);
        $this->assertSame(2, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
        $this->assertTrue(
            NotificationEvent::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('dedupe_key')
                ->count() === 2
        );
    }

    public function test_same_tenant_event_and_dedupe_key_is_duplicate(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'd-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $attrs = [
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => 'stable-key',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ];

        $svc = app(NotificationDedupeService::class);
        $r1 = $svc->tryCreateEvent($attrs);
        $r2 = $svc->tryCreateEvent($attrs);

        $this->assertFalse($r1['duplicate']);
        $this->assertTrue($r2['duplicate']);
        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_different_tenant_not_duplicate(): void
    {
        $t1 = Tenant::query()->create(['name' => 'A', 'slug' => 'a-'.substr(uniqid(), -10), 'status' => 'active']);
        $t2 = Tenant::query()->create(['name' => 'B', 'slug' => 'b-'.substr(uniqid(), -10), 'status' => 'active']);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $svc = app(NotificationDedupeService::class);

        $base = [
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => 'same',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ];

        $this->assertFalse($svc->tryCreateEvent([...$base, 'tenant_id' => $t1->id])['duplicate']);
        $this->assertFalse($svc->tryCreateEvent([...$base, 'tenant_id' => $t2->id])['duplicate']);
        $this->assertSame(2, NotificationEvent::query()->count());
    }

    public function test_different_event_key_not_duplicate(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'd-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $svc = app(NotificationDedupeService::class);

        $this->assertFalse($svc->tryCreateEvent([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => 'k',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ])['duplicate']);

        $this->assertFalse($svc->tryCreateEvent([
            'tenant_id' => $tenant->id,
            'event_key' => 'lead.created',
            'subject_type' => 'Lead',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => 'k',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ])['duplicate']);

        $this->assertSame(2, NotificationEvent::query()->count());
    }

    /**
     * Dedupe uniqueness is (tenant_id, event_key, dedupe_key) only — subject_id does not create a second event.
     */
    public function test_same_dedupe_key_duplicate_even_when_subject_id_differs(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'd-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $svc = app(NotificationDedupeService::class);

        $base = [
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'severity' => 'normal',
            'dedupe_key' => 'same-subject-agnostic',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ];

        $this->assertFalse($svc->tryCreateEvent([...$base, 'subject_id' => 1])['duplicate']);
        $this->assertTrue($svc->tryCreateEvent([...$base, 'subject_id' => 999])['duplicate']);
        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_row_inserted_before_second_try_yields_duplicate_without_query_exception(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'd-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('a', 'b', null, null, []);
        $svc = app(NotificationDedupeService::class);

        $attrs = [
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => 'pre-existing',
            'payload_json' => $payload->toArray(),
            'actor_user_id' => null,
            'occurred_at' => now(),
        ];

        NotificationEvent::factory()->create($attrs);

        $out = $svc->tryCreateEvent($attrs);
        $this->assertTrue($out['duplicate']);
        $this->assertNull($out['event']);
        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }
}
