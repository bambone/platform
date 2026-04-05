<?php

namespace Tests\Unit\Notifications;

use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;
use App\Models\Tenant;
use App\NotificationCenter\NotificationSchedulePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationSchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_allows_all_when_schedule_empty(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        $sub = new NotificationSubscription([
            'schedule_json' => null,
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $policy = app(NotificationSchedulePolicy::class);
        $this->assertTrue($policy->allowsImmediateDelivery($sub, $event));
    }

    public function test_inside_window_allows(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'Europe/Moscow',
        ]);
        // Monday 2026-04-06 10:00 Moscow
        Carbon::setTestNow(Carbon::parse('2026-04-06 10:00:00', 'Europe/Moscow'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'from' => '09:00',
                'to' => '22:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'low',
        ]);

        $policy = app(NotificationSchedulePolicy::class);
        $this->assertTrue($policy->allowsImmediateDelivery($sub, $event));
    }

    public function test_outside_window_skips_non_critical(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 23:30:00', 'Europe/Moscow'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'from' => '09:00',
                'to' => '22:00',
                'critical_bypass' => true,
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $policy = app(NotificationSchedulePolicy::class);
        $this->assertFalse($policy->allowsImmediateDelivery($sub, $event));
    }

    public function test_critical_bypass_allows_critical_outside_window(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 23:30:00', 'Europe/Moscow'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'from' => '09:00',
                'to' => '22:00',
                'critical_bypass' => true,
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'critical',
        ]);

        $policy = app(NotificationSchedulePolicy::class);
        $this->assertTrue($policy->allowsImmediateDelivery($sub, $event));
    }

    public function test_user_timezone_override_used_when_present(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00', 'Asia/Tokyo'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'user_timezone_override' => 'Asia/Tokyo',
                'from' => '09:00',
                'to' => '22:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $policy = app(NotificationSchedulePolicy::class);
        $this->assertTrue($policy->allowsImmediateDelivery($sub, $event));
    }

    public function test_invalid_schedule_timezone_skipped_when_user_timezone_override_valid(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00', 'Asia/Tokyo'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'user_timezone_override' => 'Asia/Tokyo',
                'timezone' => 'Not/A/RealZone',
                'from' => '09:00',
                'to' => '22:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_user_timezone_override_wins_over_schedule_timezone_when_both_valid(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        // 09:30 UTC = 12:30 Moscow — outside 09:00–10:00 in Moscow, inside that window in UTC.
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 30, 0, 'UTC'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'user_timezone_override' => 'UTC',
                'timezone' => 'Europe/Moscow',
                'from' => '09:00',
                'to' => '10:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_overnight_window_allows_late_night(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 02:30:00', 'Europe/Moscow'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'from' => '22:00',
                'to' => '06:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_overnight_window_blocks_midday(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00', 'Europe/Moscow'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'from' => '22:00',
                'to' => '06:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertFalse(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_from_equals_to_treated_as_whole_day_for_time_check(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 15:00:00', 'UTC'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'UTC',
                'from' => '09:00',
                'to' => '09:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_empty_days_array_allows_any_weekday_with_time_match(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-07 10:00:00', 'UTC'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'UTC',
                'days' => [],
                'from' => '09:00',
                'to' => '11:00',
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_days_only_without_time_window_blocks_weekend(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 4, 11, 12, 0, 0, 'UTC'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'UTC',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertFalse(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_days_only_without_time_window_allows_weekday(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 12, 0, 0, 'UTC'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'UTC',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'normal',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_critical_bypass_allows_critical_on_disallowed_weekday(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 4, 11, 12, 0, 0, 'UTC'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'UTC',
                'days' => [1, 2, 3, 4, 5],
                'critical_bypass' => true,
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'critical',
        ]);

        $this->assertTrue(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }

    public function test_critical_bypass_does_not_apply_to_high_severity(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 's-'.substr(uniqid(), -10),
            'status' => 'active',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-04-06 23:30:00', 'Europe/Moscow'));

        $sub = new NotificationSubscription([
            'schedule_json' => [
                'timezone' => 'Europe/Moscow',
                'from' => '09:00',
                'to' => '22:00',
                'critical_bypass' => true,
            ],
        ]);
        $event = new NotificationEvent([
            'tenant_id' => $tenant->id,
            'severity' => 'high',
        ]);

        $this->assertFalse(app(NotificationSchedulePolicy::class)->allowsImmediateDelivery($sub, $event));
    }
}
