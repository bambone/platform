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

        $public = $this->sumUnderPrefix($publicDisk, $publicPrefix, 'public');
        $private = $this->sumUnderPrefix($privateDisk, $privatePrefix, 'private');

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
            brandingBytes: $public['branding'] + $private['branding'],
            mediaBytes: $public['media'] + $private['media'],
            seoBytes: $public['seo'] + $private['seo'],
            otherBytes: $public['other'] + $private['other'],
        );
    }

    /**
     * @param  'public'|'private'  $zone
     * @return array{bytes: int, count: int, branding: int, media: int, seo: int, other: int}
     */
    private function sumUnderPrefix(Filesystem $disk, string $prefix, string $zone): array
    {
        $prefix = trim($prefix, '/');
        $totalBytes = 0;
        $count = 0;
        $branding = 0;
        $media = 0;
        $seo = 0;
        $other = 0;

        try {
            $files = $disk->allFiles($prefix);
        } catch (Throwable) {
            return [
                'bytes' => 0,
                'count' => 0,
                'branding' => 0,
                'media' => 0,
                'seo' => 0,
                'other' => 0,
            ];
        }

        foreach (array_chunk($files, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $path) {
                try {
                    $size = $disk->size($path);
                    if ($size < 0) {
                        continue;
                    }
                    $totalBytes += $size;
                    $count++;
                    $category = $this->categorizePath($path, $zone);
                    match ($category) {
                        'branding' => $branding += $size,
                        'media' => $media += $size,
                        'seo' => $seo += $size,
                        default => $other += $size,
                    };
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return [
            'bytes' => $totalBytes,
            'count' => $count,
            'branding' => $branding,
            'media' => $media,
            'seo' => $seo,
            'other' => $other,
        ];
    }

    /**
     * @param  'public'|'private'  $zone
     */
    private function categorizePath(string $path, string $zone): string
    {
        if ($zone === 'public') {
            if (str_contains($path, '/public/media/')) {
                return 'media';
            }
            if (str_contains($path, '/public/themes/')) {
                return 'branding';
            }
            if (str_contains($path, '/public/site/seo-backups')
                || preg_match('#/public/site/seo(/|$)#', $path) === 1) {
                return 'seo';
            }

            return 'other';
        }

        if (str_contains($path, '/private/site/seo/') || preg_match('#/private/site/seo$#', $path) === 1) {
            return 'seo';
        }
        if (str_contains($path, '/private/media/')) {
            return 'media';
        }

        return 'other';
    }
}
