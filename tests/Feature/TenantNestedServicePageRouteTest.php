<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantNestedServicePageRouteTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithHost(string $host): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'NestedSvc',
            'slug' => 'nested-svc',
            'theme_key' => 'expert_pr',
            'locale' => 'en',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $host,
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        return $tenant->fresh();
    }

    public function test_nested_services_slug_renders_via_dedicated_route(): void
    {
        $host = 'nested-svc.apex.test';
        $tenant = $this->tenantWithHost($host);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Media outreach',
            'slug' => 'services/demo-lane',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $this->withServerVariables(['HTTP_HOST' => $host, 'SERVER_NAME' => $host]);
        $res = $this->get('http://'.$host.'/services/demo-lane');
        $res->assertOk();
        $res->assertSee('demo-lane', false);
    }
}
