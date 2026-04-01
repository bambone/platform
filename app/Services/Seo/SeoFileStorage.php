<?php

namespace App\Services\Seo;

use App\Models\Tenant;
use App\Models\TenantSeoFile;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageArea;
use App\Support\Storage\TenantStorageDisks;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SeoFileStorage
{
    public function diskName(): string
    {
        return TenantStorageDisks::privateDiskName();
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function snapshotFileName(string $type): string
    {
        return $type === TenantSeoFile::TYPE_ROBOTS_TXT
            ? 'robots.txt'
            : 'sitemap.xml';
    }

    /** Полный логический путь на приватном диске (как в {@see TenantStorage::privatePath}). */
    private function snapshotLogicalPath(string $type): string
    {
        return TenantStorageArea::PrivateSeo->relativeBase().'/'.$this->snapshotFileName($type);
    }

    public function snapshotRelativePath(int $tenantId, string $type): string
    {
        return TenantStorage::for($tenantId)->privatePathInArea(
            TenantStorageArea::PrivateSeo,
            $this->snapshotFileName($type)
        );
    }

    public function snapshotExistsOnDisk(int $tenantId, string $type): bool
    {
        return TenantStorage::for($tenantId)->existsInArea(
            TenantStorageArea::PrivateSeo,
            $this->snapshotFileName($type)
        );
    }

    public function readSnapshot(int $tenantId, string $type): ?string
    {
        return TenantStorage::for($tenantId)->getPrivate($this->snapshotLogicalPath($type));
    }

    /**
     * @return array{path: string, filename: string}
     */
    public function createBackup(int $tenantId, string $type, string $currentContent): array
    {
        $stamp = CarbonImmutable::now()->format('Y-m-d_H-i');
        $name = $type === TenantSeoFile::TYPE_ROBOTS_TXT
            ? "robots_{$stamp}.txt"
            : "sitemap_{$stamp}.xml";
        $ts = TenantStorage::for($tenantId);
        if (! $ts->putPrivateAtomicInArea(TenantStorageArea::PrivateSeoBackups, $name, $currentContent)) {
            throw new RuntimeException('Failed to write SEO backup file.');
        }

        $fullPath = $ts->privatePathInArea(TenantStorageArea::PrivateSeoBackups, $name);

        return ['path' => $fullPath, 'filename' => $name];
    }

    public function writeSnapshot(int $tenantId, string $type, string $content): void
    {
        $ts = TenantStorage::for($tenantId);
        if (! $ts->putPrivateAtomicInArea(TenantStorageArea::PrivateSeo, $this->snapshotFileName($type), $content)) {
            throw new RuntimeException('Failed to write SEO snapshot file.');
        }
    }

    public function publicUrlForPath(Tenant $tenant, string $relativeFile): string
    {
        $base = app(TenantCanonicalPublicBaseUrl::class)->resolve($tenant);

        return $base.'/'.ltrim($relativeFile, '/');
    }
}
