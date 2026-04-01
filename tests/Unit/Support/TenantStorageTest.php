<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
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

        Storage::disk('r2-public')->assertExists('tenants/1/public/site/x.txt');
        Storage::disk('r2-private')->assertExists('tenants/1/private/site/seo/y.txt');
        $this->assertSame('hello', $ts->getPublic('site/x.txt'));
        $this->assertSame('world', $ts->getPrivate('site/seo/y.txt'));
    }
}
