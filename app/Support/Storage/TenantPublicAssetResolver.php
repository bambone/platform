<?php

namespace App\Support\Storage;

use App\Models\Tenant;
use App\Services\Seo\TenantCanonicalPublicBaseUrl;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Разрешение значений из JSON секций / настроек в публичный URL для img и CSS background.
 * Поддерживает legacy http(s) URL и object keys вида {@code tenants/{id}/public/...}.
 *
 * При {@see MediaDeliveryMode::Local} — same-origin путь {@code /media/tenants/{id}/public/...} (nginx или dev-fallback).
 * При {@see MediaDeliveryMode::R2} — прямой URL объекта (CDN/R2) как раньше.
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
            $local = self::rewriteHttpUrlForLocalMediaDelivery($v, $tenantId);
            if ($local !== null) {
                return $local;
            }

            return self::rewriteTenantPublicStorageUrlIfCdnConfigured($v, $tenantId) ?? $v;
        }

        if (preg_match('#^tenants/(\d+)/public/(.+)$#', $v, $m) === 1) {
            $id = (int) $m[1];
            if ($id !== $tenantId) {
                return null;
            }
            $relativeUnderPublic = $m[2];

            return self::safePublicUrl($tenantId, $relativeUnderPublic);
        }

        if (str_starts_with($v, 'tenants/')) {
            return null;
        }

        return self::safePublicUrl($tenantId, ltrim($v, '/'));
    }

    /**
     * Абсолютный URL для OG / писем при delivery=local (канонический хост тенанта).
     *
     * @return non-empty-string|null
     */
    public static function absoluteUrlForTenantPublicPath(Tenant $tenant, string $pathUnderPublicSegment): ?string
    {
        try {
            $url = self::publicAssetUrlForRequestContext((int) $tenant->id, $pathUnderPublicSegment, forceAbsolute: true);
            $url = trim($url);

            return $url !== '' ? $url : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return non-empty-string|null
     */
    private static function safePublicUrl(int $tenantId, string $pathUnderPublicSegment): ?string
    {
        try {
            $url = self::publicAssetUrlForRequestContext($tenantId, $pathUnderPublicSegment, forceAbsolute: false);
            $url = trim($url);

            return $url !== '' ? $url : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return non-empty-string|null
     */
    private static function rewriteHttpUrlForLocalMediaDelivery(string $url, int $tenantId): ?string
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return null;
        }
        $modes = app(EffectiveTenantMediaModeResolver::class);
        if ($modes->effectiveDeliveryMode($tenant) !== MediaDeliveryMode::Local) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $m = null;
        if (preg_match('#/storage/tenants/(\d+)/public/(.+)$#', $path, $m) !== 1) {
            // Полный URL на CDN/R2 без сегмента /storage/… (https://media…/tenants/{id}/public/…)
            if (preg_match('#/tenants/(\d+)/public/(.+)$#', $path, $m) !== 1) {
                return null;
            }
        }
        if ((int) $m[1] !== $tenantId) {
            return null;
        }

        $rel = rawurldecode($m[2]);
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }

        if (str_starts_with($rel, 'expert_auto/')) {
            $rel = 'site/'.$rel;
        }

        try {
            $out = self::publicAssetUrlForRequestContext($tenantId, $rel, forceAbsolute: true);
        } catch (Throwable) {
            return null;
        }
        $out = trim($out);
        if ($out === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $out = self::mergeUrlQueryPreferUrlBase($out, $query);
        }
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if (is_string($fragment) && $fragment !== '') {
            $out .= '#'.$fragment;
        }

        return $out;
    }

    /**
     * Старые записи в JSON/настройках: полный URL на {@code /storage/tenants/{id}/public/...} на домене сайта.
     * При включённом CDN и облачном публичном диске переписываем на прямой R2/CDN URL.
     *
     * @return non-empty-string|null
     */
    private static function rewriteTenantPublicStorageUrlIfCdnConfigured(string $url, int $tenantId): ?string
    {
        $cdn = rtrim((string) config('tenant_storage.public_cdn_base_url', ''), '/');
        if ($cdn === '') {
            return null;
        }
        if (TenantStorageDisks::usesLocalFlyAdapter(TenantStorageDisks::publicDisk())) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('#/storage/tenants/(\d+)/public/(.+)$#', $path, $m) !== 1) {
            return null;
        }
        if ((int) $m[1] !== $tenantId) {
            return null;
        }

        $rel = rawurldecode($m[2]);
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }

        if (str_starts_with($rel, 'expert_auto/')) {
            $rel = 'site/'.$rel;
        }

        try {
            $direct = TenantStorage::forTrusted($tenantId)->publicUrl($rel);
        } catch (Throwable) {
            return null;
        }
        $direct = trim($direct);
        if ($direct === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $direct = self::mergeUrlQueryPreferUrlBase($direct, $query);
        }
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if (is_string($fragment) && $fragment !== '') {
            $direct .= '#'.$fragment;
        }

        return $direct;
    }

    /**
     * @throws Throwable
     */
    private static function publicAssetUrlForRequestContext(int $tenantId, string $pathUnderPublicSegment, bool $forceAbsolute = false): string
    {
        $pathUnderPublicSegment = ltrim(str_replace('\\', '/', $pathUnderPublicSegment), '/');
        if ($pathUnderPublicSegment === '' || str_contains($pathUnderPublicSegment, '..')) {
            return '';
        }

        if (str_starts_with($pathUnderPublicSegment, 'expert_auto/')) {
            $pathUnderPublicSegment = 'site/'.$pathUnderPublicSegment;
        }

        $tenant = Tenant::query()->find($tenantId);
        $modes = app(EffectiveTenantMediaModeResolver::class);
        $delivery = $tenant !== null
            ? $modes->effectiveDeliveryMode($tenant)
            : $modes->effectiveDeliveryMode(null);

        if ($delivery === MediaDeliveryMode::Local) {
            $objectKey = 'tenants/'.$tenantId.'/public/'.$pathUnderPublicSegment;
            TenantPublicObjectKey::assertWebExposedTenantPublicKey($objectKey, $tenantId);
            $basePath = $modes->localPublicBasePath();
            $relativeUrl = $basePath.'/'.ltrim($objectKey, '/');
            if ($forceAbsolute && $tenant !== null) {
                $origin = rtrim(app(TenantCanonicalPublicBaseUrl::class)->resolve($tenant), '/');

                return self::appendPublicUrlVersion($origin.$relativeUrl);
            }

            return self::appendPublicUrlVersion($relativeUrl);
        }

        $r2Base = $modes->r2PublicBaseUrl();
        if ($r2Base !== '') {
            $objectKey = 'tenants/'.$tenantId.'/public/'.$pathUnderPublicSegment;

            return self::appendPublicUrlVersion($r2Base.'/'.$objectKey);
        }

        $cdn = rtrim((string) config('tenant_storage.public_cdn_base_url', ''), '/');
        $useDirectCdnUrl = $cdn !== '' && ! TenantStorageDisks::usesLocalFlyAdapter(TenantStorageDisks::publicDisk());

        if ($useDirectCdnUrl) {
            return self::appendPublicUrlVersion(TenantStorage::forTrusted($tenantId)->publicUrl($pathUnderPublicSegment));
        }

        $hasHttpRoute = Route::has('tenant.public.storage')
            && (! app()->runningInConsole() || request()->route() !== null);

        if ($hasHttpRoute) {
            $routeUrl = route('tenant.public.storage', [
                'tenantId' => $tenantId,
                'path' => $pathUnderPublicSegment,
            ], absolute: true);

            return self::appendPublicUrlVersion($routeUrl);
        }

        return self::appendPublicUrlVersion(TenantStorage::forTrusted($tenantId)->publicUrl($pathUnderPublicSegment));
    }

    private static function appendPublicUrlVersion(string $url): string
    {
        $bust = trim((string) config('tenant_storage.public_url_version', ''));
        if ($bust === '') {
            return $url;
        }

        parse_str($bust, $bustParts);
        if ($bustParts === []) {
            return $url.(str_contains($url, '?') ? '&' : '?').$bust;
        }

        $existing = self::parseUrlQueryToArray($url);
        $merged = array_merge($existing, $bustParts);

        return self::replaceUrlQueryString($url, $merged);
    }

    /**
     * Merge legacy stored query into URL; keys already present on {@code $preferredUrl} win (config bust / resolver output).
     *
     * @param  non-empty-string  $preferredUrl
     * @return non-empty-string
     */
    private static function mergeUrlQueryPreferUrlBase(string $preferredUrl, string $legacyQuery): string
    {
        parse_str($legacyQuery, $legacy);
        if (! is_array($legacy)) {
            $legacy = [];
        }
        $base = self::parseUrlQueryToArray($preferredUrl);
        $merged = array_merge($legacy, $base);

        return self::replaceUrlQueryString($preferredUrl, $merged);
    }

    /**
     * @return array<string, string>
     */
    private static function parseUrlQueryToArray(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return [];
        }
        parse_str($query, $out);

        return is_array($out) ? $out : [];
    }

    /**
     * @param  non-empty-string  $url
     * @param  array<string, string>  $queryParams
     * @return non-empty-string
     */
    private static function replaceUrlQueryString(string $url, array $queryParams): string
    {
        $fragment = '';
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $fragment = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }
        $qPos = strpos($url, '?');
        $base = $qPos === false ? $url : substr($url, 0, $qPos);
        $qs = http_build_query($queryParams);

        return $base.($qs !== '' ? '?'.$qs : '').$fragment;
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
            $local = self::rewriteHttpUrlForLocalMediaDelivery($v, (int) $tenant->id);
            if ($local !== null) {
                return $local;
            }

            return self::rewriteTenantPublicStorageUrlIfCdnConfigured($v, (int) $tenant->id) ?? $v;
        }

        $ts = TenantStorage::forTrusted($tenant);
        $themeKey = $tenant->themeKey();

        $tenantId = (int) $tenant->id;
        $urlIfExists = function (string $relativeUnderPublic) use ($ts, $tenantId): ?string {
            $relativeUnderPublic = ltrim(str_replace('\\', '/', $relativeUnderPublic), '/');
            if ($relativeUnderPublic === '') {
                return null;
            }
            if (! $ts->existsPublic($relativeUnderPublic)) {
                return null;
            }

            try {
                $url = self::publicAssetUrlForRequestContext($tenantId, $relativeUnderPublic);
            } catch (Throwable) {
                return null;
            }

            return $url !== '' ? $url : null;
        };

        if (preg_match('#^tenants/'.$tenantId.'/public/(.+)$#', $v, $m)) {
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
