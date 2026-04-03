<?php

namespace App\Support\Storage;

use App\Models\Tenant;

/**
 * Разрешение значений из JSON секций / настроек в публичный URL для img и CSS background.
 * Поддерживает legacy http(s) URL и object keys вида {@code tenants/{id}/public/...} на tenant public disk.
 */
final class TenantPublicAssetResolver
{
    /**
     * @return non-empty-string|null
     */
    public static function resolve(?string $value, int $tenantId): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $v) === 1) {
            return $v;
        }

        if (preg_match('#^tenants/(\d+)/public/(.+)$#', $v, $m) === 1) {
            $id = (int) $m[1];
            if ($id !== $tenantId) {
                return null;
            }
            $relativeUnderPublic = $m[2];

            return TenantStorage::forTrusted($tenantId)->publicUrl($relativeUnderPublic);
        }

        if (str_starts_with($v, 'tenants/')) {
            return null;
        }

        return TenantStorage::forTrusted($tenantId)->publicUrl(ltrim($v, '/'));
    }

    public static function resolveForCurrentTenant(?string $value): ?string
    {
        $t = \currentTenant();
        if ($t === null) {
            return null;
        }

        return self::resolve($value, (int) $t->id);
    }

    /**
     * @return non-empty-string|null
     */
    public static function resolveForTenantModel(?string $value, ?Tenant $tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }

        return self::resolve($value, (int) $tenant->id);
    }

    /**
     * URL hero-видео только из пространства тенанта (или внешний https). Без fallback на bundled-тему _system.
     *
     * @return non-empty-string|null
     */
    public static function resolveHeroVideo(?string $value, Tenant $tenant): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $v) === 1) {
            return $v;
        }

        $ts = TenantStorage::forTrusted($tenant);
        $themeKey = $tenant->themeKey();

        $urlIfExists = function (string $relativeUnderPublic) use ($ts): ?string {
            $relativeUnderPublic = ltrim(str_replace('\\', '/', $relativeUnderPublic), '/');
            if ($relativeUnderPublic === '') {
                return null;
            }
            if (! $ts->existsPublic($relativeUnderPublic)) {
                return null;
            }

            return $ts->publicUrl($relativeUnderPublic);
        };

        $tid = (int) $tenant->id;
        if (preg_match('#^tenants/'.$tid.'/public/(.+)$#', $v, $m)) {
            return $urlIfExists($m[1]);
        }

        if (preg_match('#^tenants/\d+/public/#', $v)) {
            return null;
        }

        if (preg_match('#^images/(?:motolevins|motolevin)/videos/([^/]+\.(?:mp4|webm))$#i', $v, $m)) {
            return $urlIfExists('site/videos/'.$m[1])
                ?? $urlIfExists('themes/'.$themeKey.'/videos/'.$m[1]);
        }

        if (preg_match('#^videos/([^/]+\.(?:mp4|webm))$#i', $v, $m)) {
            return $urlIfExists('site/videos/'.$m[1]);
        }

        if (preg_match('#^[^/\\\\]+\.(?:mp4|webm)$#i', $v)) {
            return $urlIfExists('site/videos/'.$v);
        }

        if (preg_match('#^themes/[^/]+/videos/([^/]+\.(?:mp4|webm))$#i', $v, $m)) {
            return $urlIfExists('themes/'.$themeKey.'/videos/'.$m[1])
                ?? $urlIfExists('site/videos/'.$m[1]);
        }

        $rel = ltrim(str_replace('\\', '/', $v), '/');

        return $urlIfExists($rel);
    }
}
