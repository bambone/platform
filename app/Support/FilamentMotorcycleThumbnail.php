<?php

namespace App\Support;

use App\Models\Motorcycle;

/**
 * Единый плейсхолдер и URL обложки для таблиц Filament (tenant admin).
 */
final class FilamentMotorcycleThumbnail
{
    public static function placeholderDataUrl(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect width="96" height="96" rx="12" fill="#374151"/><path fill="#6b7280" d="M28 64h40v6H28zm6-32a10 10 0 0 1 10-10h8a10 10 0 0 1 10 10v18H34V32z"/><text x="48" y="86" text-anchor="middle" fill="#d1d5db" font-size="10" font-family="ui-sans-serif,system-ui,sans-serif">Нет фото</text></svg>';

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }

    public static function coverUrlOrPlaceholder(?Motorcycle $motorcycle): string
    {
        $url = $motorcycle?->cover_url;

        return filled($url) ? (string) $url : self::placeholderDataUrl();
    }
}
