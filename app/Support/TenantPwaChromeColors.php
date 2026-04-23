<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;

/**
 * Единый источник цветов Chrome/PWA: поля manifest и meta theme-color.
 */
final class TenantPwaChromeColors
{
    private const DEFAULT_DARK = '#0c0c0e';

    private const ADVOCATE_TOOLBAR = '#f4f1eb';

    public static function themeColor(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return self::DEFAULT_DARK;
        }

        $raw = $tenant->pushSettings?->pwa_theme_color;
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        if ($tenant->themeKey() === 'advocate_editorial') {
            return self::ADVOCATE_TOOLBAR;
        }

        if ($tenant->themeKey() === 'black_duck') {
            return '#0A1220';
        }

        return self::DEFAULT_DARK;
    }

    public static function backgroundColor(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return self::DEFAULT_DARK;
        }

        $raw = $tenant->pushSettings?->pwa_background_color;
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        return self::DEFAULT_DARK;
    }
}
