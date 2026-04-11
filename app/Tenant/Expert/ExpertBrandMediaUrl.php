<?php

namespace App\Tenant\Expert;

use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;

/**
 * Нормализует URL бренд-фото expert_auto: в БД могут быть устаревшие пути (другой tenant_id, удалённый public/tenants/…).
 * Актуальный URL всегда строится через {@see TenantStorage::publicUrl} для текущего тенанта.
 */
final class ExpertBrandMediaUrl
{
    /** Имена файлов из сидера / команды tenant:push-brand-assets-from-docs */
    private const KNOWN_BRAND_FILES = [
        'gallery-1.jpg', 'gallery-2.jpg', 'gallery-3.jpg',
        'portrait.jpg', 'credentials-bg.jpg', 'process-accent.jpg', 'hero.jpg',
        'video-intro.mp4',
    ];

    public static function resolve(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        $tenant = \currentTenant();
        if ($tenant === null || $tenant->themeKey() !== 'expert_auto') {
            return $stored;
        }

        $path = parse_url($stored, PHP_URL_PATH);
        $path = is_string($path) ? $path : $stored;
        if (! preg_match('#([^/]+\.(?:jpe?g|mp4|webm))$#i', $path, $m)) {
            return $stored;
        }
        $file = $m[1];

        $inBrandPath = str_contains($path, '/site/brand/')
            || str_contains($path, '/public/site/brand/');
        if (! $inBrandPath && ! self::isKnownBrandFile($file)) {
            return $stored;
        }

        $fresh = TenantStorage::forTrusted($tenant)->publicUrl('site/brand/'.$file);

        // video-intro.mp4: сброс CDN/браузерного кеша при замене файла по тому же пути
        if (strtolower($file) === 'video-intro.mp4') {
            $ver = TenantSetting::getForTenant((int) $tenant->id, 'brand.intro_video_ver', '');
            if (is_string($ver) && $ver !== '') {
                $sep = str_contains($fresh, '?') ? '&' : '?';

                return $fresh.$sep.'v='.rawurlencode($ver);
            }
        }

        return $fresh;
    }

    private static function isKnownBrandFile(string $file): bool
    {
        return in_array(strtolower($file), array_map('strtolower', self::KNOWN_BRAND_FILES), true);
    }
}
