<?php

namespace App\Support;

use App\Models\Motorcycle;

/**
 * Импорт обложки из устаревшего поля cover_image в Spatie (коллекция cover).
 * Используется миграцией БД и сидером переноса Bike → Motorcycle.
 */
final class MotorcycleLegacyCoverImporter
{
    public static function absolutePathFromLegacyValue(?string $coverImage): ?string
    {
        if (! filled($coverImage)) {
            return null;
        }

        $path = ltrim((string) $coverImage, '/');
        if (str_starts_with($path, 'motolevins/')) {
            $full = public_path('images/'.$path);

            return is_file($full) ? $full : null;
        }
        if (str_starts_with($path, 'bikes/')) {
            $legacy = trim((string) config('themes.legacy_asset_url_prefix', 'images/motolevins'), '/');
            $full = public_path($legacy.'/'.$path);

            return is_file($full) ? $full : null;
        }
        if (str_starts_with($path, 'images/')) {
            $full = public_path($path);

            return is_file($full) ? $full : null;
        }

        $storage = storage_path('app/public/'.$path);

        return is_file($storage) ? $storage : null;
    }

    /**
     * Добавляет файл в коллекцию cover, если её ещё нет и путь валиден.
     */
    public static function importToCoverCollectionIfMissing(Motorcycle $motorcycle, ?string $legacyCoverImageValue): bool
    {
        if ($motorcycle->getFirstMedia('cover') !== null) {
            return false;
        }

        $absolute = self::absolutePathFromLegacyValue($legacyCoverImageValue);
        if ($absolute === null) {
            return false;
        }

        $motorcycle->addMedia($absolute)->toMediaCollection('cover');

        return true;
    }
}
