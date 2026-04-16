<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

use App\Rules\EditorialGalleryMaterialSourceUrlRule;

/**
 * Разрешённые для link-preview fetch URL: только http(s), полный хост, без опасных схем.
 * Общая логика с {@see EditorialGalleryMaterialSourceUrlRule}.
 */
final class LinkPreviewHttpUrlValidator
{
    public const ERROR_SCHEME = 'invalid_scheme';

    public const ERROR_HOST = 'invalid_host';

    public const ERROR_PARSE = 'parse_failed';

    /**
     * @return array{ok: true, url: string, host: string}|array{ok: false, error: string}
     */
    public static function validateForFetch(string $raw): array
    {
        $v = trim($raw);
        if ($v === '') {
            return ['ok' => false, 'error' => self::ERROR_PARSE];
        }

        $lower = strtolower($v);
        foreach (['javascript:', 'data:', 'vbscript:'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return ['ok' => false, 'error' => self::ERROR_SCHEME];
            }
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v) === 1) {
            return ['ok' => false, 'error' => self::ERROR_PARSE];
        }

        if (str_starts_with($v, '//')) {
            return ['ok' => false, 'error' => self::ERROR_SCHEME];
        }

        if (! str_starts_with($lower, 'http://') && ! str_starts_with($lower, 'https://')) {
            return ['ok' => false, 'error' => self::ERROR_SCHEME];
        }

        $parsed = parse_url($v);
        if ($parsed === false) {
            return ['ok' => false, 'error' => self::ERROR_PARSE];
        }

        $host = $parsed['host'] ?? null;
        if (! is_string($host) || trim($host) === '') {
            return ['ok' => false, 'error' => self::ERROR_HOST];
        }

        return ['ok' => true, 'url' => $v, 'host' => strtolower($host)];
    }
}
