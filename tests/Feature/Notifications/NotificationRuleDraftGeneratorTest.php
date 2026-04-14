<?php

namespace Tests\Feature\Notifications;

use App\Models\CrmRequest;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\NotificationCenter\NotificationRuleDraftGenerator;
use App\Services\CurrentTenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class NotificationRuleDraftGeneratorTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    public function test_creates_rules_per_distinct_pair_and_skips_on_second_run(): void
    {
        $tenant = $this->createNotificationTenant();
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        CrmRequest::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'A',
            'phone' => '+70000000001',
            'email' => null,
            'message' => 'x',
            'request_type' => 'tenant_booking',
            'source' => 'form_a',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'last_activity_at' => now(),
        ]);
        CrmRequest::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'B',
            'phone' => '+70000000002',
            'email' => null,
            'message' => 'y',
            'request_type' => 'contact',
            'source' => 'form_b',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'last_activity_at' => now(),
        ]);

        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        $out = app(NotificationRuleDraftGenerator::class)->generateForCurrentUser();

        $this->assertSame(2, $out['created']);
        $this->assertSame(0, $out['skipped']);
        $this->assertSame(2, NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->count());

        $out2 = app(NotificationRuleDraftGenerator::class)->generateForCurrentUser();
        $this->assertSame(0, $out2['created']);
        $this->assertSame(2, $out2['skipped']);
    }
}
