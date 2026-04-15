<?php

namespace App\Tenant\Expert;

/**
 * Нормализация share-URL для встраивания и хранения в JSON.
 *
 * VK: страницу ролика {@code vk.com/video-…} при сохранении можно канонизировать на {@code vkvideo.ru} (тот же путь).
 * URL {@code video_ext.php?…&hash=…} не переписываем на другой хост — иначе подпись {@code hash} перестаёт действовать
 * и плеер показывает «Видеофайл не найден». Iframe строим на том же хосте, что и во входной ссылке (vk.com или vkvideo.ru).
 */
final class VideoEmbedUrlNormalizer
{
    private const VK_COM_WATCH_HOSTS = ['vk.com', 'www.vk.com', 'm.vk.com'];

    /**
     * Если пользователь вставил фрагмент {@code <iframe … src="…">}, возвращает URL из {@code src}.
     * Иначе возвращает строку как есть (после trim).
     */
    public static function extractShareUrlFromPaste(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || stripos($raw, '<iframe') === false) {
            return $raw;
        }
        if (preg_match('/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $raw, $m) === 1) {
            return trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/\bsrc\s*=\s*([^\s>]+)/i', $raw, $m) === 1) {
            return trim(html_entity_decode(trim($m[1], '"\''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $raw;
    }

    /**
     * Для VK: короткая ссылка на страницу ролика или {@code video_ext.php} без {@code hash} часто не играет
     * во внешнем iframe («Видеофайл не найден»), хотя oid/id верные. Нужна ссылка из «Кода для вставки» с {@code hash=…}.
     */
    public static function vkEmbedProbablyMissingHash(string $shareUrl): bool
    {
        $shareUrl = trim(self::extractShareUrlFromPaste($shareUrl));
        if ($shareUrl === '' || (! str_starts_with($shareUrl, 'http://') && ! str_starts_with($shareUrl, 'https://'))) {
            return false;
        }
        $parts = parse_url($shareUrl);
        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if (! in_array($host, [
            'vk.com', 'www.vk.com', 'm.vk.com',
            'vkvideo.ru', 'www.vkvideo.ru', 'm.vkvideo.ru',
        ], true)) {
            return false;
        }
        $path = str_replace('\\', '/', (string) ($parts['path'] ?? ''));
        if ($path !== '' && preg_match('#^/video(-?\d+)_(\d+)(?:/|\?|$)#', $path) === 1) {
            return true;
        }
        if ($path !== '' && strcasecmp(basename($path), 'video_ext.php') === 0) {
            $query = $parts['query'] ?? '';
            if (! is_string($query) || $query === '') {
                return true;
            }
            parse_str($query, $q);
            $hash = $q['hash'] ?? '';

            return ! is_string($hash) || $hash === '';
        }

        return false;
    }

    /**
     * Канонизация URL страницы ролика VK при записи в БД (Expert: Галерея и др.).
     * Только {@code vk.com} / {@code www} / {@code m} и только путь {@code /video-…} или {@code …/video_ext.php}.
     */
    public static function normalizeVkShareUrlForStorage(string $shareUrl): string
    {
        $shareUrl = trim(self::extractShareUrlFromPaste($shareUrl));
        if ($shareUrl === '' || (! str_starts_with($shareUrl, 'http://') && ! str_starts_with($shareUrl, 'https://'))) {
            return $shareUrl;
        }
        $parts = parse_url($shareUrl);
        if (! is_array($parts) || empty($parts['host'])) {
            return $shareUrl;
        }
        $host = strtolower((string) $parts['host']);
        if (! in_array($host, self::VK_COM_WATCH_HOSTS, true)) {
            return $shareUrl;
        }
        $path = (string) ($parts['path'] ?? '');
        $pathSlash = str_replace('\\', '/', $path);
        $isVideoPage = $pathSlash !== '' && preg_match('#^/video(-?\d+)_(\d+)(?:/|\?|$)#', $pathSlash) === 1;
        $isVideoExt = $pathSlash !== '' && strcasecmp(basename($pathSlash), 'video_ext.php') === 0;
        if (! $isVideoPage && ! $isVideoExt) {
            return $shareUrl;
        }
        if ($isVideoExt) {
            return $shareUrl;
        }
        $query = isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        return 'https://vkvideo.ru'.$pathSlash.$query.$fragment;
    }

    /** @return non-empty-string|null */
    public static function toIframeSrc(string $provider, string $shareUrl): ?string
    {
        $provider = strtolower(trim($provider));
        $shareUrl = trim($shareUrl);
        if ($shareUrl === '' || $provider === '') {
            return null;
        }

        return match ($provider) {
            'youtube' => self::youtube($shareUrl),
            'vk' => self::vk($shareUrl),
            default => null,
        };
    }

    /** @return non-empty-string|null */
    private static function youtube(string $url): ?string
    {
        $url = self::extractShareUrlFromPaste($url);
        if (stripos($url, '<') !== false) {
            return null;
        }
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        if (! self::youtubeHostAllowed($host)) {
            return null;
        }

        $pathTrim = trim((string) ($parts['path'] ?? ''), '/');
        $id = null;

        if ($host === 'youtu.be') {
            $segments = $pathTrim === '' ? [] : explode('/', $pathTrim);
            if (isset($segments[0]) && $segments[0] === 'shorts' && isset($segments[1]) && $segments[1] !== '') {
                $id = $segments[1];
            } elseif (isset($segments[0]) && $segments[0] !== '') {
                $id = $segments[0];
            }
        }

        if ($id === null && in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com'], true)) {
            if (! empty($parts['query'])) {
                parse_str((string) $parts['query'], $q);
                if (! empty($q['v']) && is_string($q['v'])) {
                    $id = $q['v'];
                }
            }
            if ($id === null && $pathTrim !== '') {
                if (preg_match('#^(?:shorts|embed|live)/([a-zA-Z0-9_-]{6,64})(?:/|$)#', $pathTrim, $m) === 1) {
                    $id = $m[1];
                }
            }
        }

        if ($id === null || $id === '' || ! preg_match('/^[a-zA-Z0-9_-]{6,64}$/', $id)) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id).'?rel=0';
    }

    private static function youtubeHostAllowed(string $host): bool
    {
        return in_array($host, ['youtu.be', 'www.youtube.com', 'youtube.com', 'm.youtube.com'], true);
    }

    /** @return non-empty-string|null */
    private static function vk(string $url): ?string
    {
        $url = self::extractShareUrlFromPaste($url);
        if (stripos($url, '<') !== false) {
            return null;
        }
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        if (! self::vkHostAllowed($host)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = $parts['query'] ?? null;

        if ($path !== '' && strcasecmp(basename($path), 'video_ext.php') === 0 && is_string($query) && $query !== '') {
            parse_str($query, $q);
            if (empty($q['oid']) || empty($q['id'])) {
                return null;
            }
            $query = self::vkVideoExtQueryEnsureHd($query);

            return self::vkVideoExtIframeBaseForShareHost($host).'?'.$query;
        }

        if ($path !== '' && preg_match('#^/video(-?\d+)_(\d+)(?:/|\?|$)#', $path, $m) === 1) {
            $owner = $m[1];
            $vid = $m[2];

            return self::vkVideoExtIframeBaseForShareHost($host).'?oid='.rawurlencode($owner).'&id='.rawurlencode($vid).'&hd=2';
        }

        return null;
    }

    /**
     * Для {@code video_ext} без {@code hash} в примерах VK часто есть {@code hd=2}.
     * Если {@code hash} уже есть — query не трогаем: подпись привязана к точной строке параметров;
     * дописывание {@code hd=2} ломало встраивание (ответ200, но «Видеофайл не найден»).
     */
    private static function vkVideoExtQueryEnsureHd(string $query): string
    {
        if (preg_match('/(^|&)hash=/', $query) === 1) {
            return $query;
        }
        if (preg_match('/(^|&)hd=/', $query) === 1) {
            return $query;
        }

        return $query.'&hd=2';
    }

    /** @return non-empty-string */
    private static function vkVideoExtIframeBaseForShareHost(string $host): string
    {
        if (in_array($host, ['vkvideo.ru', 'www.vkvideo.ru', 'm.vkvideo.ru'], true)) {
            return 'https://vkvideo.ru/video_ext.php';
        }

        return 'https://vk.com/video_ext.php';
    }

    private static function vkHostAllowed(string $host): bool
    {
        return in_array($host, [
            'vk.com', 'www.vk.com', 'm.vk.com',
            'vkvideo.ru', 'www.vkvideo.ru', 'm.vkvideo.ru',
        ], true);
    }
}
