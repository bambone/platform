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
        $this->assertSame('page-builder/a.jpg', $rowsImages[0]['path_under_zone']);
    }

    public function test_is_allowed_object_key(): void
    {
        $svc = new TenantFileCatalogService;
        $ok = TenantStorage::forTrusted(3)->publicPath('site/logo/x.png');
        $bad = 'tenants/3/public/other/x.png';

        $this->assertTrue($svc->isAllowedObjectKey(3, $ok));
        $this->assertFalse($svc->isAllowedObjectKey(3, $bad));
    }

    public function test_themes_path_is_listed_allowed_but_not_deletable(): void
    {
        $svc = new TenantFileCatalogService;
        $themes = TenantStorage::forTrusted(5)->publicPath('themes/moto/hero.png');

        $this->assertTrue($svc->isAllowedObjectKey(5, $themes));
        $this->assertTrue($svc->isThemesObjectKey(5, $themes));
        $this->assertFalse($svc->isDeletableObjectKey(5, $themes));

        $site = TenantStorage::forTrusted(5)->publicPath('site/brand/x.png');
        $this->assertTrue($svc->isDeletableObjectKey(5, $site));
    }

    public function test_hydrate_file_metadata_graceful_when_object_disappeared(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());

        $disk = TenantStorageDisks::publicDiskName();
        $path = TenantStorage::forTrusted(1)->publicPath('site/brand/vanish.jpg');
        Storage::disk($disk)->put($path, 'ok');

        $svc = new TenantFileCatalogService;
        $rows = $svc->listLightForTenant(1, TenantFileCatalogService::FILTER_ALL);
        $this->assertCount(1, $rows);
        Storage::disk($disk)->delete($path);

        $hydrated = $svc->hydrateFileMetadata(1, $rows);
        $this->assertCount(1, $hydrated);
        $this->assertSame(0, $hydrated[0]['size']);
        $this->assertNull($hydrated[0]['last_modified']);
    }
}
