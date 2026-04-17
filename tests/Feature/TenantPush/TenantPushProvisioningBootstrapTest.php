<?php

namespace Tests\Feature\TenantPush;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPushSettings;
use App\Services\Tenancy\TenantProvisioningService;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantPushProvisioningBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_twice_is_idempotent_for_core_entities(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('slug', 'lite')->firstOrFail();

        $tenant = Tenant::query()->create([
            'name' => 'Prov T',
            'slug' => 'provt',
            'status' => 'trial',
            'plan_id' => $plan->id,
        ]);

        $svc = app(TenantProvisioningService::class);
        $svc->bootstrapAfterTenantCreated($tenant, null);
        $svc->bootstrapAfterTenantCreated($tenant, null);

        $this->assertNotNull(app(TenantStorageQuotaService::class)->ensureQuotaRecord($tenant));
        $this->assertNotNull(TenantPushSettings::query()->where('tenant_id', $tenant->id)->first());
        $domains = TenantDomain::query()->where('tenant_id', $tenant->id)->count();
        $this->assertSame(1, $domains);
    }

    public function test_plan_default_id_for_onboarding_prefers_lite(): void
    {
        $this->seed(PlanSeeder::class);
        $liteId = Plan::query()->where('slug', 'lite')->value('id');
        $this->assertNotNull($liteId);
        $this->assertSame((int) $liteId, Plan::defaultIdForOnboarding());
    }

    public function test_plan_default_id_when_lite_inactive_uses_first_active_plan(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::query()->where('slug', 'lite')->update(['is_active' => false]);
        $proId = Plan::query()->where('slug', 'pro')->where('is_active', true)->value('id');
        $this->assertNotNull($proId);
        $this->assertSame((int) $proId, Plan::defaultIdForOnboarding());
    }

    public function test_plan_default_id_is_null_when_no_active_plans(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::query()->update(['is_active' => false]);
        $this->assertNull(Plan::defaultIdForOnboarding());
    }
}
