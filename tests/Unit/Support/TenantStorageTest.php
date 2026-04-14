<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class TenantStorageTest extends TestCase
{
    public function test_for_tenant_model_builds_distinct_public_and_private_paths(): void
    {
        $tenant = new Tenant;
        $tenant->id = 42;

        $ts = TenantStorage::for($tenant);

        $this->assertSame('tenants/42', $ts->root());
        $this->assertSame('tenants/42/public/site/logo', $ts->publicPath('site/logo'));
        $this->assertSame('tenants/42/private/site/seo', $ts->privatePath('site/seo'));
        $this->assertNotSame($ts->publicPath('x'), $ts->privatePath('x'));
    }

    public function test_for_accepts_int_tenant_id(): void
    {
        $ts = TenantStorage::for(7);

        $this->assertSame('tenants/7/public/media/12', $ts->publicPath('media/12'));
    }

    public function test_rooted_path_supports_unscoped_public_media_segment(): void
    {
        $this->assertSame(
            'tenants/_unscoped/public/media/9',
            TenantStorage::rootedPath('_unscoped', 'public/media/9')
        );
    }

    public function test_for_current_throws_without_tenant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TenantStorage::forCurrent();
    }

    public function test_system_pool_prefix_constant(): void
    {
        $this->assertSame('tenants/_system', TenantStorage::SYSTEM_POOL_PREFIX);
    }

    public function test_put_public_and_private_use_configured_r2_style_disks(): void
    {
        Storage::fake('r2-public');
        Storage::fake('r2-private');
        config([
            'tenant_storage.enforce_current_tenant_context' => false,
            'tenant_storage.public_disk' => 'r2-public',
            'tenant_storage.private_disk' => 'r2-private',
        ]);

        $ts = TenantStorage::forTrusted(1);
        $this->assertTrue($ts->putPublic('site/x.txt', 'hello'));
        $this->assertTrue($ts->putPrivate('site/seo/y.txt', 'world'));

        $this->assertTrue(Storage::disk('r2-public')->exists('tenants/1/public/site/x.txt'));
        $this->assertTrue(Storage::disk('r2-private')->exists('tenants/1/private/site/seo/y.txt'));
        $this->assertSame('hello', $ts->getPublic('site/x.txt'));
        $this->assertSame('world', $ts->getPrivate('site/seo/y.txt'));
    }

    public function test_exists_public_true_when_object_exists_only_on_public_mirror_disk(): void
    {
        Storage::fake('public');
        Storage::fake('tenant-public-mirror');
        config([
            'tenant_storage.enforce_current_tenant_context' => false,
            'tenant_storage.public_disk' => 'public',
            'tenant_storage.public_mirror_disk' => 'tenant-public-mirror',
        ]);

        $key = 'tenants/1/public/site/brand/logo-header.png';
        Storage::disk('tenant-public-mirror')->put($key, 'x');
        $this->assertFalse(Storage::disk('public')->exists($key));

        $ts = TenantStorage::forTrusted(1);
        $this->assertTrue($ts->existsPublic('site/brand/logo-header.png'));
    }

    public function test_merged_public_write_options_adds_cache_control_for_cloud_disk(): void
    {
        config(['tenant_storage.public_object_cache_control' => 'public, max-age=120']);

        $nonLocal = Mockery::mock(FilesystemAdapter::class);
        $fly = Mockery::mock(\League\Flysystem\FilesystemAdapter::class);
        $nonLocal->shouldReceive('getAdapter')->andReturn($fly);

        $merged = TenantStorage::mergedOptionsForPublicObjectWrite($nonLocal, [
            'ContentType' => 'image/webp',
        ]);

        $this->assertSame('public, max-age=120', $merged['CacheControl']);
        $this->assertSame('image/webp', $merged['ContentType']);
        $this->assertSame('public', $merged['visibility']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
