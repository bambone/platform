<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationEvent;
use App\Models\Tenant;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\NotificationPayloadDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationAfterCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_outside_transaction_persists_event_immediately(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'ac-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
        );

        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_nested_transaction_rollback_does_not_leave_events(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'ac-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        try {
            DB::transaction(function () use ($recorder, $tenant, $payload): void {
                $recorder->record(
                    $tenant->id,
                    'crm_request.created',
                    'CrmRequest',
                    1,
                    $payload,
                );
                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_outer_rollback_drops_notification_event(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'ac-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        DB::beginTransaction();
        $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
        );
        DB::rollBack();

        $this->assertSame(0, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_inner_transaction_commits_but_outer_rollback_drops_event(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'ac-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        DB::beginTransaction();
        try {
            DB::transaction(function () use ($recorder, $tenant, $payload): void {
                $recorder->record(
                    $tenant->id,
                    'crm_request.created',
                    'CrmRequest',
                    1,
                    $payload,
                );
            });
        } finally {
            DB::rollBack();
        }

        $this->assertSame(0, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_outer_commit_persists_notification_event(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'ac-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        $payload = new NotificationPayloadDto('t', 'b', null, null, []);
        $recorder = app(NotificationEventRecorder::class);

        DB::beginTransaction();
        $recorder->record(
            $tenant->id,
            'crm_request.created',
            'CrmRequest',
            1,
            $payload,
        );
        DB::commit();

        $this->assertSame(1, NotificationEvent::query()->where('tenant_id', $tenant->id)->count());
    }
}
