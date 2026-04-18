<?php

namespace Tests\Feature\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Tenant\CurrentTenant;
use App\TenantSiteSetup\SetupItemStateService;
use App\TenantSiteSetup\SetupProgressCache;
use App\TenantSiteSetup\SetupProgressService;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantSiteSetupProgressTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_summary_counts_applicable_r1_items_for_expert_auto_tenant(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_r1', ['theme_key' => 'expert_auto']);
        $this->actingAsTenant($tenant);

        $summary = app(SetupProgressService::class)->computeSummary($tenant);

        $this->assertSame(14, $summary['applicable_count']);
        $this->assertSame(0, $summary['completed_count']);
    }

    public function test_programs_item_not_applicable_when_theme_is_not_expert_auto(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_no_prog', ['theme_key' => 'motorcycle_catalog']);
        $this->actingAsTenant($tenant);

        $summary = app(SetupProgressService::class)->computeSummary($tenant);

        $this->assertSame(12, $summary['applicable_count']);
    }

    public function test_completed_only_from_data_site_name(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_site', ['theme_key' => 'expert_auto']);
        $this->actingAsTenant($tenant);

        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Мой сайт');

        SetupProgressCache::forget((int) $tenant->id);
        $summary = app(SetupProgressService::class)->computeSummary($tenant);

        $this->assertGreaterThanOrEqual(1, $summary['completed_count']);
    }

    public function test_mark_not_needed_fails_when_not_allowed(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_nn', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $this->expectException(HttpException::class);
        $this->actingAs($user);
        app(SetupItemStateService::class)->markNotNeeded($tenant, $user, 'settings.site_name', 'do_later', null);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_feature_flag_hides_widget_when_disabled(): void
    {
        config(['features.tenant_site_setup_framework' => false]);
        $this->assertFalse(TenantSiteSetupFeature::enabled());
    }

    public function test_progress_summary_cache_key_includes_user_id(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_cache_key', ['theme_key' => 'expert_auto']);
        $u1 = User::factory()->create(['status' => 'active']);
        $u1->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);
        $u2->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->actingAs($u1);
        $k1 = SetupProgressCache::key((int) $tenant->id, Auth::id());
        $this->actingAs($u2);
        $k2 = SetupProgressCache::key((int) $tenant->id, Auth::id());

        $this->assertNotSame($k1, $k2);
    }

    public function test_progress_cache_forget_bumps_revision_invalidating_prior_keys(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_cache_rev', ['theme_key' => 'expert_auto']);
        $before = SetupProgressCache::key((int) $tenant->id, 99);
        SetupProgressCache::forget((int) $tenant->id);
        $after = SetupProgressCache::key((int) $tenant->id, 99);

        $this->assertNotSame($before, $after);
    }

    private function actingAsTenant(Tenant $tenant): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $this->actingAs($user);
    }
}
