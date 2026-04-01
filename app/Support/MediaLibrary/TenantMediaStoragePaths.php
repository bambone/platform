<?php

namespace App\Support\MediaLibrary;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Канонический путь медиа на public-диске: {@code tenants/{id}/public/media/{media_id}/}.
 * Совместимость: поиск существующего файла по цепочке старых расположений.
 */
final class TenantMediaStoragePaths
{
    /**
     * @return non-empty-string
     */
    public static function legacyFlatBasePath(Media $media): string
    {
        $prefix = config('media-library.prefix', '');
        $id = $media->getKey();

        return $prefix !== '' ? $prefix.'/'.$id : (string) $id;
    }

    /**
     * @return non-empty-string|null
     */
    public static function previousTenantFlatModelFolder(Media $media): ?string
    {
        $model = $media->model;
        if (! $model instanceof Model || ! in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            return null;
        }
        $tid = $model->getAttribute('tenant_id');
        if ($tid === null || $tid === '') {
            return null;
        }
        $prefix = config('media-library.prefix', '');
        $id = $media->getKey();
        $base = TenantStorage::rootedPath((string) $tid, (string) $id);

        return $prefix !== '' ? $prefix.'/'.$base : $base;
    }

    /**
     * Без сегмента {@code public/}: {@code tenants/{id}/media/{media_id}}.
     *
     * @return non-empty-string|null
     */
    public static function legacyTenantMediaWithoutPublicSegment(Media $media): ?string
    {
        $model = $media->model;
        if (! $model instanceof Model || ! in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            return null;
        }
        $tid = $model->getAttribute('tenant_id');
        if ($tid === null || $tid === '') {
            return null;
        }
        $prefix = config('media-library.prefix', '');
        $id = $media->getKey();
        $base = TenantStorage::rootedPath((string) $tid, 'media/'.$id);

        return $prefix !== '' ? $prefix.'/'.$base : $base;
    }

    /**
     * Канонический базовый каталог (новые загрузки).
     *
     * @return non-empty-string
     */
    public static function canonicalPublicMediaBase(Media $media): string
    {
        $prefix = config('media-library.prefix', '');
        $tenantSegment = self::tenantSegment($media);
        $id = $media->getKey();
        $base = $tenantSegment === '_unscoped'
            ? TenantStorage::rootedPath('_unscoped', 'public/media/'.$id)
            : TenantStorage::for((int) $tenantSegment)->publicPath('media/'.$id);

        return $prefix !== '' ? $prefix.'/'.$base : $base;
    }

    /**
     * Первый базовый путь, где на диске реально лежит основной файл (для URL и чтения).
     *
     * @return non-empty-string
     */
    public static function resolveBasePathForExistingFile(Media $media): string
    {
        $media->loadMissing('model');
        $disk = Storage::disk($media->disk);
        $file = $media->file_name;

        // Cloud disks: each exists() is remote latency; DB + migration target canonical layout.
        if (! TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            return self::canonicalPublicMediaBase($media);
        }

        $candidates = [];
        $candidates[] = self::legacyFlatBasePath($media);
        $p = self::previousTenantFlatModelFolder($media);
        if ($p !== null) {
            $candidates[] = $p;
        }
        $m = self::legacyTenantMediaWithoutPublicSegment($media);
        if ($m !== null) {
            $candidates[] = $m;
        }
        $candidates[] = self::canonicalPublicMediaBase($media);

        foreach ($candidates as $base) {
            if ($disk->exists($base.'/'.$file)) {
                return $base;
            }
        }

        return self::canonicalPublicMediaBase($media);
    }

    public static function usesLegacyFlatLayout(Media $media): bool
    {
        $disk = Storage::disk($media->disk);
        if (! TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            return false;
        }

        $relativeFile = self::legacyFlatBasePath($media).'/'.$media->file_name;

        return $disk->exists($relativeFile);
    }

    /**
     * @return non-empty-string
     */
    public static function tenantSegment(Media $media): string
    {
        $model = $media->model;

        if ($model instanceof Model && in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            $tid = $model->getAttribute('tenant_id');
            if ($tid !== null && $tid !== '') {
                return (string) $tid;
            }
        }

        return '_unscoped';
    }
}
