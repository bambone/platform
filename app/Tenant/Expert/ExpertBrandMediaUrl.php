<?php

namespace App\Tenant\Expert;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantPublicAssetResolver;
use App\Support\Storage\TenantStorage;

/**
 * Нормализует URL бренд-фото expert_auto: в БД могут быть устаревшие пути (другой tenant_id, удалённый public/tenants/…).
 * Публичные URL собираются через {@see TenantPublicAssetResolver} (прямой CDN при {@code TENANT_STORAGE_PUBLIC_CDN_URL} + облачный диск, иначе /storage/… на origin).
 */
final class ExpertBrandMediaUrl
{
    /** Имена файлов из сидера / команды tenant:push-brand-assets-from-docs */
    private const KNOWN_BRAND_FILES = [
        'gallery-1.jpg', 'gallery-2.jpg', 'gallery-3.jpg',
        'portrait.jpg', 'credentials-bg.jpg', 'process-accent.jpg', 'hero.jpg', 'magas-hero.png', 'magas-hero.jpg',
        'video-intro.mp4',
    ];

    public static function resolve(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        $tenant = \currentTenant();
        /** expert_pr хранит тот же {@code site/brand/…}, что семейство expert (Magas bootstrap). Внешние URL не подменяем. */
        if ($tenant === null || ! in_array($tenant->themeKey(), ['expert_auto', 'advocate_editorial', 'black_duck', 'expert_pr'], true)) {
            return $stored;
        }

        $tenantId = (int) $tenant->id;

        $viaResolver = TenantPublicAssetResolver::resolveForCurrentTenant($stored);
        if ($viaResolver !== null && $viaResolver !== '') {
            return self::appendIntroVideoVersion($viaResolver, $stored, $tenant);
        }

        $path = parse_url($stored, PHP_URL_PATH);
        $path = is_string($path) ? $path : $stored;
        $path = str_replace('\\', '/', (string) $path);

        $underBrand = null;
        if (preg_match('#(?:^|/)(?:public/)?site/brand/(.+)$#i', $path, $m)) {
            $underBrand = $m[1];
        }

        if ($underBrand === null) {
            if (preg_match('#^site/brand/(.+)$#i', ltrim($path, '/'), $m)) {
                $underBrand = $m[1];
            }
        }

        if ($underBrand !== null && is_string($underBrand) && $underBrand !== '') {
            if (preg_match('#^(.+\.(?:jpe?g|png|gif|webp|avif|mp4|webm))$#i', $underBrand) === 1) {
                $rel = 'site/brand/'.$underBrand;
                $fresh = TenantPublicAssetResolver::resolve($rel, $tenantId)
                    ?? TenantStorage::forTrusted($tenant)->publicUrl($rel);

                return self::appendIntroVideoVersion($fresh, $stored, $tenant);
            }
        }

        if (! preg_match('#([^/]+\.(?:jpe?g|png|gif|webp|avif|mp4|webm))$#i', $path, $m)) {
            return $stored;
        }
        $file = $m[1];

        $inBrandPath = str_contains($path, 'site/brand/');
        if (! $inBrandPath && ! self::isKnownBrandFile($file)) {
            return $stored;
        }

        $fresh = TenantPublicAssetResolver::resolve('site/brand/'.$file, $tenantId)
            ?? TenantStorage::forTrusted($tenant)->publicUrl('site/brand/'.$file);

        return self::appendIntroVideoVersion($fresh, $stored, $tenant);
    }

    private static function appendIntroVideoVersion(string $fresh, string $stored, Tenant $tenant): string
    {
        $path = parse_url($fresh, PHP_URL_PATH);
        $path = is_string($path) ? $path : $fresh;
        $fromStored = parse_url($stored, PHP_URL_PATH);
        $fromStored = is_string($fromStored) ? $fromStored : $stored;

        $isIntro = str_ends_with(strtolower($path), 'video-intro.mp4')
            || str_ends_with(strtolower($fromStored), 'video-intro.mp4');
        if (! $isIntro) {
            return $fresh;
        }

        $ver = TenantSetting::getForTenant((int) $tenant->id, 'brand.intro_video_ver', '');
        if (! is_string($ver) || $ver === '') {
            return $fresh;
        }
        $sep = str_contains($fresh, '?') ? '&' : '?';

        return $fresh.$sep.'v='.rawurlencode($ver);
    }

    private static function isKnownBrandFile(string $file): bool
    {
        return in_array(strtolower($file), array_map('strtolower', self::KNOWN_BRAND_FILES), true);
    }
}
