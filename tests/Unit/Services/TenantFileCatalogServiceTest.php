<?php

namespace Tests\Unit\Services;

use App\Services\TenantFiles\TenantFileCatalogService;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantFileCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_current_tenant_prefix_and_respects_filter(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());

        $disk = TenantStorageDisks::publicDiskName();
        $a = TenantStorage::forTrusted(1)->publicPath('site/page-builder/a.jpg');
        $b = TenantStorage::forTrusted(2)->publicPath('site/page-builder/b.jpg');
        Storage::disk($disk)->put($a, 'x');
        Storage::disk($disk)->put($b, 'y');

        $svc = new TenantFileCatalogService;
        $rowsA = $svc->listForTenant(1, TenantFileCatalogService::FILTER_ALL);
        $pathsA = array_column($rowsA, 'path');
        $this->assertContains($a, $pathsA);
        $this->assertNotContains($b, $pathsA);

        $rowsThemes = $svc->listForTenant(1, TenantFileCatalogService::FILTER_THEMES);
        $this->assertSame([], $rowsThemes);

        $rowsImages = $svc->listForTenant(1, TenantFileCatalogService::FILTER_IMAGES);
        $this->assertCount(1, $rowsImages);
        $this->assertTrue($rowsImages[0]['is_image']);
    }

    public function test_is_allowed_object_key(): void
    {
        $svc = new TenantFileCatalogService;
        $ok = TenantStorage::forTrusted(3)->publicPath('site/logo/x.png');
        $bad = 'tenants/3/public/other/x.png';

        $this->assertTrue($svc->isAllowedObjectKey(3, $ok));
        $this->assertFalse($svc->isAllowedObjectKey(3, $bad));
    }
}
