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

    /** Видео для галереи / пикера: только MP4 и WebM (MVP). */
    public const FILTER_VIDEOS = 'videos';

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'ico'];

    /** @var list<string> */
    public const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /**
     * Листинг путей без обращений {@code size}/{@code lastModified} к диску (важно для S3/R2).
     *
     * @return list<array{
     *     path: string,
     *     name: string,
     *     path_under_zone: string,
     *     segment: 'site'|'themes'|'media',
     *     is_image: bool,
     *     public_url: string|null
     * }>
     */
    public function listLightForTenant(int $tenantId, string $typeFilter = self::FILTER_ALL, ?string $search = null): array
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

            try {
                $paths = $disk->allFiles($rootPrefix);
            } catch (\Throwable) {
                continue;
            }

            foreach ($paths as $path) {
                $path = (string) $path;
                if (! $this->isUnderAllowedRoot($path, $roots)) {
                    continue;
                }

                $basename = basename($path);
                $pathUnderZone = '';
                if ($path !== $rootPrefix && str_starts_with($path, $rootPrefix.'/')) {
                    $pathUnderZone = substr($path, strlen($rootPrefix) + 1);
                }
                if ($searchNorm !== '') {
                    $hay = Str::lower($basename.' '.$pathUnderZone);
                    if (! str_contains($hay, $searchNorm)) {
                        continue;
                    }
                }

                $ext = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));
                $isImage = in_array($ext, self::IMAGE_EXTENSIONS, true);
                $isVideo = in_array($ext, self::VIDEO_EXTENSIONS, true);
                $isDoc = $ext !== '' && ! $isImage && ! $isVideo;

                if ($typeFilter === self::FILTER_IMAGES && ! $isImage) {
                    continue;
                }
                if ($typeFilter === self::FILTER_VIDEOS && ! $isVideo) {
                    continue;
                }
                if ($typeFilter === self::FILTER_DOCUMENTS && ! $isDoc) {
                    continue;
                }

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
                    'path_under_zone' => $pathUnderZone,
                    'segment' => $segment,
                    'is_image' => $isImage,
                    'public_url' => $publicUrl,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['path_under_zone'] ?? '', $b['path_under_zone'] ?? ''));

        return $out;
    }

    /**
     * Дополняет строки из {@see listLightForTenant} размером и датой изменения (по одному запросу к диску на файл).
     *
     * @param  list<array{path: string, name: string, path_under_zone?: string, segment: string, is_image: bool, public_url: string|null}>  $lightRows
     * @return list<array{
     *     path: string,
     *     name: string,
     *     path_under_zone: string,
     *     size: int,
     *     last_modified: int|null,
     *     segment: string,
     *     is_image: bool,
     *     public_url: string|null
     * }>
     */
    public function hydrateFileMetadata(int $tenantId, array $lightRows): array
    {
        unset($tenantId);
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $out = [];
        foreach ($lightRows as $row) {
            $path = $row['path'];
            $size = 0;
            $lastModified = null;
            try {
                $size = (int) ($disk->size($path) ?: 0);
                $lastModified = $disk->lastModified($path) ?: null;
            } catch (\Throwable) {
            }
            $out[] = [
                'path' => $path,
                'name' => $row['name'],
                'path_under_zone' => (string) ($row['path_under_zone'] ?? ''),
                'size' => $size,
                'last_modified' => $lastModified,
                'segment' => $row['segment'],
                'is_image' => $row['is_image'],
                'public_url' => $row['public_url'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *     path: string,
     *     name: string,
     *     path_under_zone: string,
     *     size: int,
     *     last_modified: int|null,
     *     segment: 'site'|'themes'|'media',
     *     is_image: bool,
     *     public_url: string|null
     * }>
     */
    public function listForTenant(int $tenantId, string $typeFilter = self::FILTER_ALL, ?string $search = null): array
    {
        $light = $this->listLightForTenant($tenantId, $typeFilter, $search);

        return $this->hydrateFileMetadata($tenantId, $light);
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
     * Удаление из UI разрешено только вне иерархии {@code themes/} (тема — read-only: листинг без delete).
     */
    public function isDeletableObjectKey(int $tenantId, string $path): bool
    {
        if (! $this->isAllowedObjectKey($tenantId, $path)) {
            return false;
        }
        $path = str_replace('\\', '/', trim($path));
        $ts = TenantStorage::forTrusted($tenantId);
        $themes = rtrim($ts->publicPath('themes'), '/');

        if ($path === $themes) {
            return false;
        }

        if (str_starts_with($path, $themes.'/')) {
            return false;
        }

        return true;
    }

    public function isThemesObjectKey(int $tenantId, string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        $ts = TenantStorage::forTrusted($tenantId);
        $themes = rtrim($ts->publicPath('themes'), '/');

        return $path === $themes || str_starts_with($path, $themes.'/');
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
