<?php

namespace Tests\Unit\Seo;

use App\Models\TenantSeoFile;
use App\Services\Seo\SeoFileStorage;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoFileStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TenantStorageDisks::privateDiskName());
    }

    public function test_writes_snapshot_under_tenant_isolated_path(): void
    {
        $storage = app(SeoFileStorage::class);
        $storage->writeSnapshot(7, TenantSeoFile::TYPE_ROBOTS_TXT, "User-agent: *\nDisallow:\n");

        $path = $storage->snapshotRelativePath(7, TenantSeoFile::TYPE_ROBOTS_TXT);
        $this->assertSame(TenantStorage::for(7)->privatePath('site/seo/robots.txt'), $path);
        $this->assertTrue(Storage::disk($storage->diskName())->exists($path));
    }

    public function test_backup_uses_separate_directory(): void
    {
        $storage = app(SeoFileStorage::class);
        $info = $storage->createBackup(7, TenantSeoFile::TYPE_ROBOTS_TXT, 'old');

        $this->assertStringStartsWith(TenantStorage::for(7)->privatePath('site/seo-backups').'/', $info['path']);
        $this->assertMatchesRegularExpression('/^robots_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.txt$/', $info['filename']);
        $this->assertTrue(Storage::disk($storage->diskName())->exists($info['path']));
    }
}
