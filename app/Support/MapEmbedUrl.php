<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Решает, можно ли подставить URL в iframe как карту.
 * Обычные ссылки «открыть в Яндекс/Гугл картах» в iframe не работают (X-Frame / не embed).
 */
final class MapEmbedUrl
{
    public static function iframeSrcForHttpLink(string $url): ?string
    {
        $u = trim($url);
        if ($u === '' || preg_match('#^https?://#i', $u) !== 1) {
            return null;
        }

        $lower = strtolower($u);

        if (str_contains($lower, 'yandex.ru/map-widget')) {
            return $u;
        }
        if (str_contains($lower, 'yandex.ru/maps/embed')) {
            return $u;
        }
        if (preg_match('#google\.[^/]+/maps/embed#', $lower) === 1) {
            return $u;
        }
        if (str_contains($lower, 'openstreetmap.org/export/embed')) {
            return $u;
        }
        if (str_contains($lower, 'embed.2gis.com')) {
            return $u;
        }

        return null;
    }
}
