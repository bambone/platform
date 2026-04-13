<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\RememberTenantCatalogLocation;
use App\Http\Middleware\ResolveTenantPublicSeo;
use App\Support\Storage\TenantPublicAssetResolver;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPublicAssetResolverHttpTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_http_local_public_disk_with_cdn_config_uses_storage_route_not_cdn(): void
    {
        $tenant = $this->createTenantWithActiveDomain('reshttplocal');
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rb-reshttp-'.uniqid('', true);
        mkdir($root, 0777, true);
        config([
            'filesystems.disks.public.root' => $root,
            'tenant_storage.public_disk' => 'public',
            'tenant_storage.public_cdn_base_url' => 'https://cdn.example.com',
        ]);

        Route::middleware(['web', EnsureTenantContext::class, RememberTenantCatalogLocation::class, ResolveTenantPublicSeo::class])
            ->get('/__test_pub_resolver_local', function () use ($tenant) {
                abort_unless(currentTenant() && (int) currentTenant()->id === (int) $tenant->id, 404);

                return response()->json([
                    'url' => TenantPublicAssetResolver::resolve('site/logo.png', (int) $tenant->id),
                ]);
            });

        $host = $this->tenancyHostForSlug('reshttplocal');
        $response = $this->getJson('http://'.$host.'/__test_pub_resolver_local');

        $response->assertOk();
        $url = (string) $response->json('url');
        $this->assertStringContainsString('/storage/tenants/'.$tenant->id.'/public/site/logo.png', $url);
        $this->assertStringNotContainsString('cdn.example.com', $url);
    }

    public function test_http_cloud_public_disk_with_cdn_config_uses_direct_cdn_url(): void
    {
        $tenant = $this->createTenantWithActiveDomain('reshttpcloud');
        $relativeKey = 'tenants/'.$tenant->id.'/public/site/logo.png';

        $nonLocal = Mockery::mock(FilesystemAdapter::class);
        $fly = Mockery::mock(\League\Flysystem\FilesystemAdapter::class);
        $nonLocal->shouldReceive('getAdapter')->andReturn($fly);

        Storage::partialMock()
            ->shouldReceive('disk')
            ->andReturnUsing(function (string $name) use ($nonLocal) {
                if ($name === 'r2-resolver-http') {
                    return $nonLocal;
                }

                return (new FilesystemManager($this->app))->disk($name);
            });

        config([
            'tenant_storage.public_disk' => 'r2-resolver-http',
            'tenant_storage.public_cdn_base_url' => 'https://cdn.example.com',
            'tenant_storage.public_url_version' => '',
        ]);

        Route::middleware(['web', EnsureTenantContext::class, RememberTenantCatalogLocation::class, ResolveTenantPublicSeo::class])
            ->get('/__test_pub_resolver_cloud', function () use ($tenant, $relativeKey) {
                abort_unless(currentTenant() && (int) currentTenant()->id === (int) $tenant->id, 404);

                return response()->json([
                    'url' => TenantPublicAssetResolver::resolve('site/logo.png', (int) $tenant->id),
                    'expected' => 'https://cdn.example.com/'.$relativeKey,
                ]);
            });

        $host = $this->tenancyHostForSlug('reshttpcloud');
        $response = $this->getJson('http://'.$host.'/__test_pub_resolver_cloud');

        $response->assertOk();
        $this->assertSame($response->json('expected'), $response->json('url'));
    }
}
