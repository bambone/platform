<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantViewResolver;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\CurrentTenant;
use App\Terminology\TenantTerminologyService;
use App\Terminology\TerminologyHumanizer;
use App\Themes\ThemeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Route;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        if (! app()->bound(CurrentTenant::class)) {
            return null;
        }

        return app(CurrentTenant::class)->tenant;
    }
}

if (! function_exists('tenant_domain')) {
    function tenant_domain(): ?TenantDomain
    {
        if (! app()->bound(CurrentTenant::class)) {
            return null;
        }

        return app(CurrentTenant::class)->domain;
    }
}

if (! function_exists('is_non_tenant_host')) {
    function is_non_tenant_host(): bool
    {
        if (! app()->bound(CurrentTenant::class)) {
            return true;
        }

        return app(CurrentTenant::class)->isNonTenantHost;
    }
}

if (! function_exists('is_central_domain')) {
    /**
     * @deprecated Use is_non_tenant_host()
     */
    function is_central_domain(): bool
    {
        return is_non_tenant_host();
    }
}

if (! function_exists('currentTenant')) {
    function currentTenant(): ?Tenant
    {
        return tenant();
    }
}

if (! function_exists('tenant_term')) {
    /**
     * Resolved display label for a domain term in the current tenant context.
     */
    function tenant_term(string $termKey, ?string $locale = null): string
    {
        $t = currentTenant();
        if ($t === null) {
            return TerminologyHumanizer::humanize($termKey);
        }

        return app(TenantTerminologyService::class)->label($t, $termKey, $locale);
    }
}

if (! function_exists('tenant_view')) {
    /**
     * Resolve a tenant public view by logical name (theme + engine fallback) and return a View instance.
     *
     * @param  array<string, mixed>  $data
     */
    function tenant_view(string $logical, array $data = []): View
    {
        $resolved = app(TenantViewResolver::class)->resolve($logical);

        return view($resolved, $data);
    }
}

if (! function_exists('tenant_branding_asset_url')) {
    /**
     * Public URL for a tenant branding file on {@see TenantStorageDisks::publicDiskName()}, or a legacy absolute URL.
     *
     * @param  non-empty-string|null  $relativePath  Object key on the public disk (e.g. tenants/1/public/site/logo/x.png)
     */
    function tenant_branding_asset_url(?string $relativePath, ?string $legacyUrl): string
    {
        $relativePath = $relativePath !== null ? trim($relativePath) : '';
        if ($relativePath !== '') {
            $key = ltrim($relativePath, '/');
            $disk = TenantStorageDisks::publicDisk();
            if (! $disk instanceof FilesystemAdapter) {
                return '';
            }
            // R2/S3: exists() is a round-trip per call — avoid on hot paths (view composer ×3).
            if (TenantStorageDisks::usesLocalFlyAdapter($disk) && ! $disk->exists($key)) {
                return '';
            }

            return $disk->url($key);
        }

        $legacyUrl = $legacyUrl !== null ? trim($legacyUrl) : '';
        if ($legacyUrl !== '') {
            return $legacyUrl;
        }

        return '';
    }
}

if (! function_exists('theme_platform_asset_url')) {
    /**
     * URL ассета платформенной темы (public/themes/{key}/…), с fallback на legacy-префикс из config/themes.php.
     *
     * @param  non-empty-string  $relativeWithinTheme  Например {@code marketing/hero-bg.png}
     */
    function theme_platform_asset_url(string $relativeWithinTheme, ?Tenant $tenant = null): string
    {
        $tenant ??= tenant();
        $key = $tenant === null
            ? (string) config('themes.default_key', 'moto')
            : $tenant->themeKey();

        return app(ThemeRegistry::class)->assetUrl($key, $relativeWithinTheme);
    }
}

if (! function_exists('tenant_theme_public_url')) {
    /**
     * Публичный URL файла темы в {@code tenants/{id}/public/…}, если файл есть; иначе пустая строка.
     *
     * @param  non-empty-string  $pathUnderTenantPublic  Например {@code site/videos/foo.mp4} (без префикса tenants/…/public/).
     */
    function tenant_theme_public_url(string $pathUnderTenantPublic): string
    {
        $t = tenant();
        if ($t === null) {
            return '';
        }
        $ts = TenantStorage::for($t);
        $disk = TenantStorageDisks::publicDisk();
        if (TenantStorageDisks::usesLocalFlyAdapter($disk) && ! $ts->existsPublic($pathUnderTenantPublic)) {
            return '';
        }

        return $ts->publicUrl($pathUnderTenantPublic);
    }
}

if (! function_exists('tenant_branding_logo_url')) {
    function tenant_branding_logo_url(): string
    {
        $t = tenant();
        if ($t === null) {
            return '';
        }

        return tenant_branding_asset_url(
            TenantSetting::getForTenant($t->id, 'branding.logo_path', ''),
            TenantSetting::getForTenant($t->id, 'branding.logo', '')
        );
    }
}

if (! function_exists('tenant_branding_favicon_url')) {
    function tenant_branding_favicon_url(): string
    {
        $t = tenant();
        if ($t === null) {
            return '';
        }

        return tenant_branding_asset_url(
            TenantSetting::getForTenant($t->id, 'branding.favicon_path', ''),
            TenantSetting::getForTenant($t->id, 'branding.favicon', '')
        );
    }
}

if (! function_exists('tenant_branding_hero_url')) {
    function tenant_branding_hero_url(): string
    {
        $t = tenant();
        if ($t === null) {
            return '';
        }

        return tenant_branding_asset_url(
            TenantSetting::getForTenant($t->id, 'branding.hero_path', ''),
            TenantSetting::getForTenant($t->id, 'branding.hero', '')
        );
    }
}

if (! function_exists('platform_marketing_hero_headline')) {
    function platform_marketing_hero_headline(): string
    {
        $c = config('platform_marketing');
        $v = (string) ($c['hero_variant'] ?? 'c');

        return (string) ($c['hero'][$v] ?? $c['hero']['c'] ?? '');
    }
}

if (! function_exists('platform_marketing_tracking_query')) {
    /**
     * Параметры для сохранения при переходах с лендинга (UTM и др.).
     *
     * @return array<string, string>
     */
    function platform_marketing_tracking_query(): array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'yclid'];
        $out = [];
        foreach ($keys as $key) {
            $v = request()->query($key);
            if (is_string($v) && $v !== '') {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}

if (! function_exists('platform_marketing_url_with_tracking')) {
    /**
     * Добавить tracking query к абсолютному или относительному URL.
     */
    function platform_marketing_url_with_tracking(string $url): string
    {
        $params = platform_marketing_tracking_query();
        if ($params === []) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.http_build_query($params);
    }
}

if (! function_exists('platform_marketing_contact_url')) {
    /**
     * URL страницы контактов с intent и сохранением UTM.
     */
    function platform_marketing_contact_url(?string $intent = null): string
    {
        $query = platform_marketing_tracking_query();

        $intents = config('platform_marketing.intent', []);
        if ($intent !== null && $intent !== '') {
            $query['intent'] = $intent;
        }

        if (Route::has('platform.contact')) {
            return route('platform.contact', $query);
        }

        $base = url('/contact');
        if ($query === []) {
            return $base;
        }

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($query);
    }
}

if (! function_exists('platform_marketing_demo_url')) {
    /**
     * URL для CTA «Посмотреть демо»: внешняя ссылка из env или /contact?intent=demo (+ UTM).
     */
    function platform_marketing_demo_url(): string
    {
        $custom = trim((string) config('platform_marketing.demo_url', ''));
        if ($custom !== '') {
            if (preg_match('#^https?://#i', $custom)) {
                return platform_marketing_url_with_tracking($custom);
            }

            return platform_marketing_url_with_tracking(url($custom));
        }

        $demoIntent = (string) (config('platform_marketing.intent.demo') ?? 'demo');

        return platform_marketing_contact_url($demoIntent);
    }
}

if (! function_exists('filament_tenant_spatie_media_preview_url')) {
    /**
     * Same-origin URL для превью в кабинете тенанта (Filament FileUpload делает fetch — без CORS на внешний CDN).
     *
     * @param  non-empty-string  $conversion
     */
    function filament_tenant_spatie_media_preview_url(?Media $media, string $conversion = ''): ?string
    {
        if ($media === null) {
            return null;
        }

        $params = ['media' => $media->getKey()];
        if ($conversion !== '') {
            $params['conversion'] = $conversion;
        }

        try {
            if (! Route::has('filament.admin.spatie-media.show')) {
                return null;
            }

            return route('filament.admin.spatie-media.show', $params, false);
        } catch (\Throwable) {
            return null;
        }
    }
}
