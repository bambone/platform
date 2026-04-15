<?php

namespace Tests\Unit\Services;

use App\Services\TenantFiles\TenantFileCatalogService;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class TenantFileCatalogServiceVideoFilterTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_filter_videos_lists_only_mp4_and_webm(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = $this->createTenantWithActiveDomain('tfc-vid');
        $tid = (int) $tenant->id;
        $ts = TenantStorage::forTrusted($tid);
        $base = $ts->publicPath('site/page-builder');
        $disk = TenantStorageDisks::publicDiskName();
        Storage::disk($disk)->put($base.'/a.mp4', 'a');
        Storage::disk($disk)->put($base.'/b.webm', 'b');
        Storage::disk($disk)->put($base.'/c.jpg', 'c');
        Storage::disk($disk)->put($base.'/d.mov', 'd');
        Storage::disk($disk)->put($base.'/e.ogv', 'e');

        $svc = new TenantFileCatalogService;
        $rows = $svc->listLightForTenant($tid, TenantFileCatalogService::FILTER_VIDEOS);
        $names = array_map(static fn (array $r): string => $r['name'], $rows);
        sort($names);
        $this->assertSame(['a.mp4', 'b.webm'], $names);
    }
}
