<?php

namespace App\Tenant\StorageQuota;

use App\Support\Storage\TenantStorageDisks;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

final class TenantStorageUsageScanner
{
    private const CHUNK_SIZE = 500;

    public function scan(int $tenantId): TenantStorageScanResult
    {
        $publicPrefix = 'tenants/'.$tenantId.'/public';
        $privatePrefix = 'tenants/'.$tenantId.'/private';

        $publicDisk = TenantStorageDisks::publicDisk();
        $privateDisk = TenantStorageDisks::privateDisk();

        $public = $this->sumUnderPrefix($publicDisk, $publicPrefix);
        $private = $this->sumUnderPrefix($privateDisk, $privatePrefix);

        $scannedAt = now();

        return new TenantStorageScanResult(
            publicBytes: $public['bytes'],
            privateBytes: $private['bytes'],
            totalBytes: $public['bytes'] + $private['bytes'],
            objectCount: $public['count'] + $private['count'],
            scannedAt: $scannedAt,
            diskBreakdown: [
                'public_disk' => TenantStorageDisks::publicDiskName(),
                'private_disk' => TenantStorageDisks::privateDiskName(),
                'public_objects' => $public['count'],
                'private_objects' => $private['count'],
            ],
        );
    }

    /**
     * @return array{bytes: int, count: int}
     */
    private function sumUnderPrefix(Filesystem $disk, string $prefix): array
    {
        $prefix = trim($prefix, '/');
        $totalBytes = 0;
        $count = 0;

        // S3 / R2: нет отдельного объекта для «папки» — ключ `tenants/1/public` не существует,
        // хотя объекты лежат под `tenants/1/public/media/...`. Проверка exists() давала 0 B.
        try {
            $files = $disk->allFiles($prefix);
        } catch (Throwable) {
            return ['bytes' => 0, 'count' => 0];
        }

        foreach (array_chunk($files, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $path) {
                try {
                    $size = $disk->size($path);
                    if ($size >= 0) {
                        $totalBytes += $size;
                        $count++;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return ['bytes' => $totalBytes, 'count' => $count];
    }
}
