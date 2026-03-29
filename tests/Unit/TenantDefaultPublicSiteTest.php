<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantDefaultPublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_public_site_name_uses_brand_then_name(): void
    {
        $t = Tenant::query()->create([
            'name' => 'Corp Name',
            'slug' => 'corp',
            'brand_name' => 'Brand X',
            'status' => 'active',
        ]);
        $this->assertSame('Brand X', $t->defaultPublicSiteName());

        $t2 = Tenant::query()->create([
            'name' => 'Only Name',
            'slug' => 'only',
            'status' => 'active',
        ]);
        $this->assertSame('Only Name', $t2->defaultPublicSiteName());
    }

    public function test_default_public_site_url_matches_current_request_host_when_domain_active(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Moto',
            'slug' => 'moto',
            'status' => 'active',
        ]);
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'moto.rentbase.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);
        $tenant->refresh();

        $req = Request::create('https://moto.rentbase.test/', 'GET', [], [], [], ['HTTP_HOST' => 'moto.rentbase.test', 'HTTPS' => 'on']);

        $this->assertSame('https://moto.rentbase.test', $tenant->defaultPublicSiteUrl($req));
    }

    public function test_default_public_site_url_falls_back_to_primary_when_request_host_unknown(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Moto',
            'slug' => 'moto',
            'status' => 'active',
        ]);
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'primary.example.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);
        $tenant->refresh();

        $req = Request::create('https://other.test/', 'GET', [], [], [], ['HTTP_HOST' => 'other.test']);

        $this->assertSame('https://primary.example.test', $tenant->defaultPublicSiteUrl($req));
    }
}
