<?php

namespace Tests\Feature\TenantPush;

use App\TenantPush\TenantPushFeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPushPublicRoutesTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function getWithHost(string $host, string $path = '/'): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;
        $url = 'http://'.$host.$path;

        return $this->call('GET', $url);
    }

    public function test_manifest_and_onesignal_worker_return_200_on_tenant_host(): void
    {
        config(['tenancy.central_domains' => ['localhost', '127.0.0.1']]);
        config(['tenancy.root_domain' => 'test']);

        $tenant = $this->createTenantWithActiveDomain('pwaroutes');
        $settings = app(TenantPushFeatureGate::class)->ensureSettings($tenant);
        $settings->is_pwa_enabled = true;
        $settings->save();

        $host = $this->tenancyHostForSlug('pwaroutes');

        $manifest = $this->getWithHost($host, '/manifest.webmanifest');
        $manifest->assertOk();
        $manifest->assertHeader('Content-Type', 'application/manifest+json');

        $worker = $this->getWithHost($host, '/push/onesignal/OneSignalSDKWorker.js');
        $worker->assertOk();
        $worker->assertSee('importScripts', false);
    }
}
