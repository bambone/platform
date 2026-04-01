<?php

namespace Tests\Unit\Support;

use App\Support\Storage\TenantStorageDisks;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalFlyAdapter;
use Mockery;
use Tests\TestCase;

class TenantStorageDisksTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_uses_local_fly_adapter_true_for_storage_fake(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $this->assertTrue(TenantStorageDisks::usesLocalFlyAdapter($disk));
    }

    public function test_uses_local_fly_adapter_false_for_non_local_adapter(): void
    {
        $inner = Mockery::mock(\League\Flysystem\FilesystemAdapter::class);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('getAdapter')->andReturn($inner);

        $this->assertFalse(TenantStorageDisks::usesLocalFlyAdapter($disk));
    }

    public function test_uses_local_fly_adapter_true_when_inner_is_local(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rb-tsd-'.uniqid('', true);
        mkdir($root, 0777, true);
        $inner = new LocalFlyAdapter($root);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('getAdapter')->andReturn($inner);

        $this->assertTrue(TenantStorageDisks::usesLocalFlyAdapter($disk));
    }
}
