<?php

namespace App\Services\TenantFiles;

use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Безопасный листинг файлов текущего tenant под {@code tenants/{id}/public/{site,themes,media}}.
 */
final class TenantFileCatalogService
{
    public const FILTER_IMAGES = 'images';

    public const FILTER_DOCUMENTS = 'documents';

    public const FILTER_THEMES = 'themes';

    public const FILTER_MEDIA = 'media';

    public const FILTER_ALL = 'all';

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'ico'];

    /**
     * @return list<array{
     *     path: string,
     *     name: string,
     *     size: int,
     *     last_modified: int|null,
     *     segment: 'site'|'themes'|'media',
     *     is_image: bool,
     *     public_url: string|null
     * }>
     */
    public function listForTenant(int $tenantId, string $typeFilter = self::FILTER_ALL, ?string $search = null): array
    {
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $ts = TenantStorage::forTrusted($tenantId);

        $roots = [
            'site' => $ts->publicPath('site'),
            'themes' => $ts->publicPath('themes'),
            'media' => $ts->publicPath(TenantStorage::MEDIA_FOLDER),
        ];

        $searchNorm = $search !== null ? Str::lower(trim($search)) : '';
        $out = [];

        foreach ($roots as $segment => $rootPrefix) {
            if ($typeFilter === self::FILTER_THEMES && $segment !== 'themes') {
                continue;
            }
            if ($typeFilter === self::FILTER_MEDIA && $segment !== 'media') {
                continue;
            }

            if (! $disk->exists($rootPrefix)) {
                continue;
            }

            foreach ($disk->allFiles($rootPrefix) as $path) {
                $path = (string) $path;
                if (! $this->isUnderAllowedRoot($path, $roots)) {
                    continue;
                }

                $basename = basename($path);
                if ($searchNorm !== '' && ! str_contains(Str::lower($basename), $searchNorm)) {
                    continue;
                }

                $ext = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));
                $isImage = in_array($ext, self::IMAGE_EXTENSIONS, true);
                $isDoc = $ext !== '' && ! $isImage;

                if ($typeFilter === self::FILTER_IMAGES && ! $isImage) {
                    continue;
                }
                if ($typeFilter === self::FILTER_DOCUMENTS && ! $isDoc) {
                    continue;
                }

                $size = (int) ($disk->size($path) ?: 0);
                $lastModified = $disk->lastModified($path);

                $publicUrl = null;
                try {
                    if (preg_match('#^tenants/\d+/public/(.+)$#', $path, $m) === 1) {
                        $publicUrl = $ts->publicUrl($m[1]);
                    }
                } catch (\Throwable) {
                    $publicUrl = null;
                }

                $out[] = [
                    'path' => $path,
                    'name' => $basename,
                    'size' => $size,
                    'last_modified' => $lastModified ?: null,
                    'segment' => $segment,
                    'is_image' => $isImage,
                    'public_url' => $publicUrl,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($b['path'], $a['path']));

        return $out;
    }

    public function isAllowedObjectKey(int $tenantId, string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        $ts = TenantStorage::forTrusted($tenantId);
        $allowed = [
            $ts->publicPath('site').'/',
            $ts->publicPath('themes').'/',
            $ts->publicPath(TenantStorage::MEDIA_FOLDER).'/',
        ];
        foreach ($allowed as $prefix) {
            if (str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $roots  segment => prefix
     */
    private function isUnderAllowedRoot(string $path, array $roots): bool
    {
        foreach ($roots as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
