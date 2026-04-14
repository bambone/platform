<?php

declare(strict_types=1);

namespace App\Support;

use App\PageBuilder\Contacts\MapProvider;

/**
 * Whitelist validation and normalization for map links stored in page builder (https only, no short links on MVP).
 */
final class SafeMapPublicUrl
{
    /**
     * Yandex map-widget URLs with encoded `ouri` / geo payloads may exceed 2k chars.
     */
    public const MAX_LENGTH = 8192;

    /**
     * @return array{0: string, 1: MapProvider}|null [normalizedUrl, provider]
     */
    public static function normalizeAndClassify(string $raw): ?array
    {
        $t = trim($raw);
        if ($t === '' || strlen($t) > self::MAX_LENGTH) {
            return null;
        }
        $lower = strtolower($t);
        foreach (['javascript:', 'data:', 'blob:', 'vbscript:'] as $deny) {
            if (str_starts_with($lower, $deny)) {
                return null;
            }
        }
        if (preg_match('/<[^>]+>/', $t) === 1) {
            return null;
        }
        if (preg_match('#^https://#i', $t) !== 1) {
            return null;
        }
        $parts = parse_url($t);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        if (strtolower((string) $parts['scheme']) !== 'https') {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        $provider = self::detectProviderForHost($host);
        if ($provider === null || $provider === MapProvider::None) {
            return null;
        }

        $canonical = self::rebuildHttpsUrl($parts);

        return [$canonical, $provider];
    }

    public static function validateMatchesProvider(string $normalizedUrl, MapProvider $provider): bool
    {
        $parts = parse_url($normalizedUrl);
        if ($parts === false || ! isset($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        $detected = self::detectProviderForHost($host);

        return $detected === $provider;
    }

    public static function detectProviderForHost(string $host): ?MapProvider
    {
        $h = strtolower($host);
        if (self::hostIsYandex($h)) {
            return MapProvider::Yandex;
        }
        if (self::hostIsGoogle($h)) {
            return MapProvider::Google;
        }
        if (self::hostIs2Gis($h)) {
            return MapProvider::TwoGis;
        }

        return null;
    }

    /**
     * Extract first https:// URL from noisy text (mixed HTML, prose).
     */
    public static function extractFirstHttpsUrl(string $text): ?string
    {
        $t = trim($text);
        if ($t === '' || strlen($t) > self::MAX_LENGTH) {
            return null;
        }
        if (preg_match('#https://[^\s<>"\'\)\]\}]+#i', $t, $m) !== 1) {
            return null;
        }
        $url = rtrim($m[0], '.,;)\'"');
        if (strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        return $url;
    }

    /**
     * Upgrade //host/... or http:// to https:// for allowlist checks.
     */
    public static function normalizeIframeSrcHttps(string $src): string
    {
        $s = trim($src);
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, '//')) {
            $s = 'https:'.$s;
        }
        if (preg_match('#^http://#i', $s) === 1) {
            $s = (string) preg_replace('#^http://#i', 'https://', $s, 1);
        }

        return $s;
    }

    /**
     * First iframe src= in HTML (editor paste or legacy migration).
     * Tolerates newlines, attribute order (e.g. width before src), and HTML entities in src.
     */
    public static function extractFirstIframeSrc(string $html): ?string
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }
        if (preg_match('/<iframe\b[^>]*?\bsrc\s*=\s*("|\')(.+?)\1/is', $html, $m) === 1) {
            $src = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $src !== '' ? $src : null;
        }
        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * First https link to a map provider inside &lt;a href="..."&gt; (Yandex “поделиться” обёртки).
     *
     * @return list<string> candidate URLs in document order
     */
    public static function extractMapAnchorHrefs(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }
        $out = [];
        if (preg_match_all('/<a\b[^>]*?\bhref\s*=\s*("|\')(https:\/\/[^"\']+)\1/is', $html, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $row) {
                $u = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($u !== '' && ! in_array($u, $out, true)) {
                    $out[] = $u;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function rebuildHttpsUrl(array $parts): string
    {
        $scheme = 'https';
        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$path.$query.$fragment;
    }

    private static function hostIsYandex(string $h): bool
    {
        // Строгий allowlist (MVP): без произвольных поддоменов *.yandex.ru.
        return in_array($h, ['yandex.ru', 'maps.yandex.ru'], true);
    }

    private static function hostIsGoogle(string $h): bool
    {
        return in_array($h, ['google.com', 'www.google.com', 'maps.google.com'], true);
    }

    private static function hostIs2Gis(string $h): bool
    {
        return in_array($h, ['2gis.ru', 'www.2gis.ru', 'go.2gis.com'], true);
    }
}
