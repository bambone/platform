<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

use App\Support\MapEmbedUrl;
use App\Support\SafeMapPublicUrl;

/**
 * Builds a safe iframe src from a normalized public map URL. No raw HTML.
 */
final class SafeMapEmbedResolver
{
    /**
     * @return array{0: ?string, 1: bool} [embedSrc or null, canEmbedThisUrl]
     */
    public static function resolveEmbedSrc(MapProvider $provider, string $normalizedHttpsUrl): array
    {
        if ($provider === MapProvider::None || $normalizedHttpsUrl === '') {
            return [null, false];
        }

        $prebuilt = MapEmbedUrl::iframeSrcForHttpLink($normalizedHttpsUrl);
        if ($prebuilt !== null && self::embedHostAllowed($provider, $prebuilt)) {
            return [self::normalizeHttps($prebuilt), true];
        }

        return match ($provider) {
            MapProvider::Yandex => self::yandexFromMapsLink($normalizedHttpsUrl),
            MapProvider::Google => self::googleResolve($normalizedHttpsUrl),
            MapProvider::TwoGis => self::twoGisResolve($normalizedHttpsUrl),
            MapProvider::None => [null, false],
        };
    }

    public static function fallbackReasonWhenNoEmbedRu(MapProvider $provider, bool $hadPrebuiltPath): string
    {
        if ($hadPrebuiltPath) {
            return '';
        }

        return match ($provider) {
            MapProvider::Yandex => 'Для встроенной карты нужна ссылка с координатами (параметры ll и z на карте Яндекса) или готовый виджет (yandex.ru/map-widget/…). Обычная ссылка поиска по адресу открывается на сайте только кнопкой.',
            MapProvider::Google => 'Встроенная карта Google в этом проекте поддерживается только для специальных ссылок «Поделиться → Карта». Обычная ссылка на Google Maps откроется во внешней вкладке.',
            MapProvider::TwoGis => '2ГИС: из окна «Поделиться» обычно приходит только ссылка, не iframe. Встраивание карты на сайт — через embed.2gis.com или отдельную интеграцию (API/виджет); обычная ссылка на 2gis.ru открывается кнопкой.',
            MapProvider::None => '',
        };
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private static function yandexFromMapsLink(string $normalizedHttpsUrl): array
    {
        $parts = parse_url($normalizedHttpsUrl);
        if ($parts === false || ! isset($parts['host'])) {
            return [null, false];
        }
        $host = strtolower((string) $parts['host']);
        if (! in_array($host, ['yandex.ru', 'maps.yandex.ru'], true)) {
            return [null, false];
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $ll = isset($query['ll']) && is_string($query['ll']) ? trim($query['ll']) : '';
        $z = isset($query['z']) && (is_string($query['z']) || is_numeric($query['z']))
            ? (string) $query['z']
            : '16';

        if ($ll !== '' && preg_match('/^[\d.,\-]+$/', str_replace('%2C', ',', rawurldecode($ll))) === 1) {
            $llForWidget = str_contains($ll, '%') ? $ll : str_replace(',', '%2C', $ll);
            $zSafe = is_numeric($z) ? (string) (int) $z : '16';
            $q = 'll='.$llForWidget.'&z='.$zSafe;
            if (isset($query['pt']) && is_string($query['pt']) && $query['pt'] !== '') {
                $q .= '&pt='.rawurlencode($query['pt']);
            }

            return ['https://yandex.ru/map-widget/v1/?'.$q, true];
        }

        return [null, false];
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private static function googleResolve(string $normalizedHttpsUrl): array
    {
        $lower = strtolower($normalizedHttpsUrl);
        if (preg_match('#google\.[^/]+/maps/embed#', $lower) === 1) {
            return [$normalizedHttpsUrl, true];
        }

        return [null, false];
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private static function twoGisResolve(string $normalizedHttpsUrl): array
    {
        $lower = strtolower($normalizedHttpsUrl);
        if (str_contains($lower, 'embed.2gis.com')) {
            return [$normalizedHttpsUrl, true];
        }

        return [null, false];
    }

    private static function embedHostAllowed(MapProvider $provider, string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        $detected = SafeMapPublicUrl::detectProviderForHost($host);

        return $detected === $provider;
    }

    private static function normalizeHttps(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }
        $scheme = 'https';

        return $scheme.'://'.strtolower((string) $parts['host'])
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
