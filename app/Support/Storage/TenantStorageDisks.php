<?php

namespace App\Support\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalFlyAdapter;

/**
 * Единая точка имён дисков tenant public/private и определения способа отдачи файлов.
 *
 * @see docs/operations/r2-tenant-storage.md
 */
final class TenantStorageDisks
{
    public static function publicDiskName(): string
    {
        return (string) config('tenant_storage.public_disk', 'public');
    }

    public static function privateDiskName(): string
    {
        return (string) config('tenant_storage.private_disk', config('seo.disk', 'local'));
    }

    public static function usesLocalFlyAdapter(Filesystem $disk): bool
    {
        if (! $disk instanceof FilesystemAdapter) {
            return false;
        }

        return $disk->getAdapter() instanceof LocalFlyAdapter;
    }

    public static function publicDisk(): Filesystem
    {
        return Storage::disk(self::publicDiskName());
    }

    public static function privateDisk(): Filesystem
    {
        return Storage::disk(self::privateDiskName());
    }

    public static function publicMirrorDiskName(): string
    {
        return (string) config('tenant_storage.public_mirror_disk', 'tenant-public-mirror');
    }

    public static function replicaPublicDiskName(): string
    {
        return (string) config('tenant_storage.replica_public_disk', 'r2-public');
    }

    public static function publicMirrorDisk(): Filesystem
    {
        return Storage::disk(self::publicMirrorDiskName());
    }

    public static function replicaPublicDisk(): Filesystem
    {
        return Storage::disk(self::replicaPublicDiskName());
    }
}
