<?php

namespace App\Services\Seo;

use App\Models\Motorcycle;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantPublicAssetResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * OG image fallback chain for tenant public SEO (absolute URLs only, public disks).
 *
 * Order: SeoMeta.og_image → entity primary image → tenant default → config fallback.
 */
final class TenantPublicOgImageResolver
{
    public function __construct(
        private TenantCanonicalPublicBaseUrl $canonicalBase,
    ) {}

    public function resolve(?SeoMeta $seo, ?Model $model, Tenant $tenant): ?string
    {
        if ($seo !== null && TenantSeoMerge::isFilled($seo->og_image)) {
            $u = $this->normalizeToAbsolutePublicUrl(trim((string) $seo->og_image), $tenant);
            if ($u !== null) {
                return $u;
            }
        }

        if ($model instanceof Motorcycle && TenantSeoMerge::isFilled($model->cover_url)) {
            $u = $this->normalizeToAbsolutePublicUrl((string) $model->cover_url, $tenant);
            if ($u !== null) {
                return $u;
            }
        }

        $tenantDefault = trim((string) TenantSetting::getForTenant($tenant->id, 'seo.default_og_image_url', ''));
        if ($tenantDefault !== '') {
            $u = $this->normalizeToAbsolutePublicUrl($tenantDefault, $tenant);
            if ($u !== null) {
                return $u;
            }
        }

        $configFallback = trim((string) config('seo.tenant_public_fallback_og_image_url', ''));
        if ($configFallback !== '') {
            $u = $this->normalizeToAbsolutePublicUrl($configFallback, $tenant);
            if ($u !== null) {
                return $u;
            }
        }

        return null;
    }

    /**
     * Accept only http(s) absolute URLs or site-relative paths on public storage (never private disks).
     */
    private function normalizeToAbsolutePublicUrl(string $raw, Tenant $tenant): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($raw, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $resolved = TenantPublicAssetResolver::resolve($raw, (int) $tenant->id) ?? $raw;
            if (preg_match('#^https?://#i', $resolved) === 1) {
                return $resolved;
            }
            $base = rtrim($this->canonicalBase->resolve($tenant), '/');

            return $base.(str_starts_with($resolved, '/') ? $resolved : '/'.$resolved);
        }

        if (preg_match('#^tenants/\d+/public/#', $raw) === 1) {
            $resolved = TenantPublicAssetResolver::resolve($raw, (int) $tenant->id);
            if ($resolved === null) {
                return null;
            }
            if (preg_match('#^https?://#i', $resolved) === 1) {
                return $resolved;
            }
            $base = rtrim($this->canonicalBase->resolve($tenant), '/');

            return $base.(str_starts_with($resolved, '/') ? $resolved : '/'.$resolved);
        }

        if (str_starts_with($raw, '//')) {
            return null;
        }

        $path = str_starts_with($raw, '/') ? $raw : '/'.$raw;
        $disk = (string) config('filesystems.default', 'local');
        if ($this->diskIsPrivate($disk)) {
            return null;
        }

        $base = rtrim($this->canonicalBase->resolve($tenant), '/');

        return $base.$path;
    }

    private function diskIsPrivate(string $disk): bool
    {
        $visibility = config('filesystems.disks.'.$disk.'.visibility');

        return $visibility === 'private';
    }
}
