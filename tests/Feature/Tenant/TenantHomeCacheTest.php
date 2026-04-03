<?php

namespace Tests\Feature\Tenant;

use App\Http\Controllers\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantHomeCacheTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_public_home_renders_on_successive_requests(): void
    {
        $tenant = $this->createTenantWithActiveDomain('homecache');
        $host = $this->tenancyHostForSlug('homecache');

        HomeController::forgetCachedPayloadForTenant($tenant->id);
        Cache::flush();

        $this->call('GET', 'http://'.$host.'/')->assertOk();
        $this->call('GET', 'http://'.$host.'/')->assertOk();
    }
}
