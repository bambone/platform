<?php

namespace Tests\Unit\TenantPush;

use App\Models\Plan;
use App\Models\TenantPushEventPreference;
use App\Models\User;
use App\TenantPush\TenantPushCrmRequestRecipientResolver;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPushCrmRequestRecipientResolverTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_selected_users_only_includes_active_tenant_panel_members(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $memberActive = User::factory()->create();
        $memberInactive = User::factory()->create();
        $stranger = User::factory()->create();

        $tenant = $this->createTenantWithActiveDomain('pushsel', [
            'plan_id' => $plan->id,
            'owner_user_id' => $memberActive->id,
        ]);

        $memberActive->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $memberInactive->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'invited']);

        TenantPushEventPreference::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => true,
                'delivery_mode' => 'immediate',
                'recipient_scope' => 'selected_users',
                'selected_user_ids_json' => [$memberActive->id, $memberInactive->id, $stranger->id, 999999],
            ],
        );

        $ids = app(TenantPushCrmRequestRecipientResolver::class)->resolveOnesignalRecipientUserIds($tenant);

        $this->assertSame([(int) $memberActive->id], $ids);
    }
}
