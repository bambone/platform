<?php

namespace Tests\Feature\Tenant;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPublicStorageCloudRedirectTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tenant_public_storage_redirects_when_disk_is_not_local(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cloudredir');
        $relative = 'tenants/'.$tenant->id.'/public/site/x.txt';

        $nonLocal = Mockery::mock(FilesystemAdapter::class);
        $fly = Mockery::mock(\League\Flysystem\FilesystemAdapter::class);
        $nonLocal->shouldReceive('getAdapter')->andReturn($fly);
        $nonLocal->shouldReceive('url')->with($relative)->andReturn('https://cdn.example/'.$relative);

        Storage::partialMock()
            ->shouldReceive('disk')
            ->andReturnUsing(function (string $name) use ($nonLocal) {
                if ($name === 'r2-pub-test') {
                    return $nonLocal;
                }

                return (new FilesystemManager($this->app))->disk($name);
            });

        config(['tenant_storage.public_disk' => 'r2-pub-test']);

        $host = $this->tenancyHostForSlug('cloudredir');
        $response = $this->get('http://'.$host.'/storage/tenants/'.$tenant->id.'/public/site/x.txt');

        $response->assertRedirect('https://cdn.example/'.$relative);
    }
}
