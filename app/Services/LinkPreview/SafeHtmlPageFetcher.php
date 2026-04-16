<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

use Illuminate\Support\Facades\Http;

/**
 * GET HTML с ручными редиректами и проверкой хоста на каждом шаге.
 */
final class SafeHtmlPageFetcher
{
    public const ERROR_HTTP = 'http_error';

    public const ERROR_TOO_LARGE = 'too_large';

    public const ERROR_NOT_HTML = 'not_html';

    public const ERROR_REDIRECT = 'redirect_invalid';

    private const MAX_REDIRECTS = 5;

    private const MAX_BYTES = 786_432; // 768 KiB

    private const TIMEOUT_SEC = 5;

    /**
     * @return array{ok: true, html: string, finalUrl: string, contentType: string}|array{ok: false, error: string, message: string, finalUrl: string}
     */
    public function fetch(string $startUrl): array
    {
        $url = $startUrl;
        $redirects = 0;

        while (true) {
            $v = LinkPreviewHttpUrlValidator::validateForFetch($url);
            if (! $v['ok']) {
                return ['ok' => false, 'error' => $v['error'], 'message' => 'Invalid URL.', 'finalUrl' => $url];
            }

            try {
                LinkPreviewHostSafetyChecker::assertResolvableHostIsPublic(parse_url($v['url'], PHP_URL_HOST) ?? '');
            } catch (LinkPreviewUnsafeHostException $e) {
                return ['ok' => false, 'error' => $e->errorCode, 'message' => $e->getMessage(), 'finalUrl' => $url];
            }

            $response = Http::timeout(self::TIMEOUT_SEC)
                ->withHeaders([
                    'User-Agent' => 'RentBaseLinkPreview/1.0',
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                ])
                ->withOptions(['allow_redirects' => false])
                ->get($v['url']);

            $status = $response->status();
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    return ['ok' => false, 'error' => self::ERROR_REDIRECT, 'message' => 'Too many redirects.', 'finalUrl' => $url];
                }
                $loc = $response->header('Location');
                if (! is_string($loc) || trim($loc) === '') {
                    return ['ok' => false, 'error' => self::ERROR_REDIRECT, 'message' => 'Redirect without Location.', 'finalUrl' => $url];
                }
                $next = self::resolveRedirectUrl($v['url'], trim($loc));
                $nv = LinkPreviewHttpUrlValidator::validateForFetch($next);
                if (! $nv['ok']) {
                    return ['ok' => false, 'error' => self::ERROR_REDIRECT, 'message' => 'Invalid redirect URL.', 'finalUrl' => $url];
                }
                $url = $nv['url'];
                $redirects++;

                continue;
            }

            if ($status < 200 || $status >= 300) {
                return ['ok' => false, 'error' => self::ERROR_HTTP, 'message' => 'HTTP '.$status, 'finalUrl' => $v['url']];
            }

            $body = $response->body();
            if (strlen($body) > self::MAX_BYTES) {
                return ['ok' => false, 'error' => self::ERROR_TOO_LARGE, 'message' => 'Response too large.', 'finalUrl' => $v['url']];
            }

            $ct = strtolower((string) $response->header('Content-Type', ''));
            if ($ct !== '' && ! str_contains($ct, 'text/html') && ! str_contains($ct, 'application/xhtml')) {
                if (! self::looksLikeHtml($body)) {
                    return ['ok' => false, 'error' => self::ERROR_NOT_HTML, 'message' => 'Not HTML.', 'finalUrl' => $v['url']];
                }
            }

            return [
                'ok' => true,
                'html' => $body,
                'finalUrl' => $v['url'],
                'contentType' => $ct,
            ];
        }
    }

    private static function resolveRedirectUrl(string $base, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $p = parse_url($base);
        if ($p === false) {
            return $location;
        }
        $scheme = $p['scheme'] ?? 'https';
        $host = $p['host'] ?? '';
        if ($host === '') {
            return $location;
        }
        $port = isset($p['port']) ? ':'.$p['port'] : '';
        $origin = $scheme.'://'.$host.$port;

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        return $origin.'/'.ltrim($location, '/');
    }

    private static function looksLikeHtml(string $chunk): bool
    {
        $s = strtolower(substr(ltrim($chunk), 0, 256));

        return str_contains($s, '<html') || str_contains($s, '<!doctype html');
    }
}
