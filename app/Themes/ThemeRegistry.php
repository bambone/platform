<?php

namespace App\Themes;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;

/**
 * Реестр платформенных тем (манифесты в resources/themes).
 */
final class ThemeRegistry
{
    /** @var array<string, ThemeDefinition> */
    private array $cache = [];

    public function get(string $themeKey): ThemeDefinition
    {
        $normalized = $this->normalizeKey($themeKey);
        if (isset($this->cache[$normalized])) {
            return $this->cache[$normalized];
        }

        $path = resource_path('themes/'.$normalized.'/theme.json');
        if (File::isFile($path)) {
            $json = File::get($path);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $def = ThemeDefinition::fromArray($data);
                $this->cache[$normalized] = $def;

                return $def;
            }
        }

        $fallback = ThemeDefinition::synthetic($normalized);
        $this->cache[$normalized] = $fallback;

        return $fallback;
    }

    public function defaultDefinition(): ThemeDefinition
    {
        return $this->get((string) config('themes.default_key', 'moto'));
    }

    /**
     * См. {@see resolveAssetUrlUncached()} — порядок разрешения URL в теле метода.
     *
     * Результат мемоизируется на время HTTP-запроса: иначе на объектном диске каждый вызов
     * {@code exists()} — отдельный round-trip, а {@code theme_platform_asset_url()} дергается из Blade десятки раз.
     */
    public function assetUrl(string $themeKey, string $relativeWithinTheme, ?Tenant $tenant = null): string
    {
        $relativeWithinTheme = ltrim(str_replace('\\', '/', $relativeWithinTheme), '/');
        $normalizedKey = $this->normalizeKey($themeKey);
        $memoKey = $normalizedKey."\0".$relativeWithinTheme."\0".($tenant?->id ?? 0);

        $request = request();
        /** @var array<string, string> $bucket */
        $bucket = $request->attributes->get('_theme_registry_asset_url', []);
        if (isset($bucket[$memoKey])) {
            return $bucket[$memoKey];
        }

        $url = $this->resolveAssetUrlUncached($themeKey, $relativeWithinTheme, $tenant);
        $bucket[$memoKey] = $url;
        $request->attributes->set('_theme_registry_asset_url', $bucket);

        return $url;
    }

    private function resolveAssetUrlUncached(string $themeKey, string $relativeWithinTheme, ?Tenant $tenant = null): string
    {
        $relativeWithinTheme = ltrim(str_replace('\\', '/', $relativeWithinTheme), '/');
        $def = $this->get($this->normalizeKey($themeKey));
        $disk = TenantStorageDisks::publicDisk();

        if ($tenant !== null && $relativeWithinTheme !== '' && $disk instanceof FilesystemAdapter) {
            $tenantKey = TenantStorage::forTrusted($tenant)->publicThemesPath($relativeWithinTheme);
            if (TenantStorageDisks::usesLocalFlyAdapter($disk)) {
                if ($disk->exists($tenantKey)) {
                    return $disk->url($tenantKey);
                }
            } elseif ($disk->exists($tenantKey)) {
                return $disk->url($tenantKey);
            }
        }

        $primary = $def->assetWebPrefix.'/'.$relativeWithinTheme;
        if ($relativeWithinTheme !== '' && is_file(public_path($primary))) {
            return asset($primary);
        }

        if ($relativeWithinTheme !== '' && $disk instanceof FilesystemAdapter && $this->systemBundledUsesObjectStorage()) {
            if (! TenantStorageDisks::usesLocalFlyAdapter($disk)) {
                return $disk->url(TenantStorage::systemBundledThemeObjectKey($def->key, $relativeWithinTheme));
            }

            $systemKey = TenantStorage::systemBundledThemeObjectKey($def->key, $relativeWithinTheme);
            if ($disk->exists($systemKey)) {
                return $disk->url($systemKey);
            }
        }

        $resourceFile = resource_path('themes/'.$def->key.'/public/'.$relativeWithinTheme);
        if ($relativeWithinTheme !== '' && is_file($resourceFile)) {
            return route('theme.platform.asset', ['theme' => $def->key, 'path' => $relativeWithinTheme]);
        }

        // Темы без theme.json / без bundled PNG (например expert_auto): не тянуть legacy motolevins для PWA-иконок.
        if ($relativeWithinTheme !== '' && str_starts_with($relativeWithinTheme, 'icons/')
            && $def->key !== 'moto') {
            $motoResource = resource_path('themes/moto/public/'.$relativeWithinTheme);
            if (is_file($motoResource)) {
                return route('theme.platform.asset', ['theme' => 'moto', 'path' => $relativeWithinTheme]);
            }
        }

        $legacy = trim((string) config('themes.legacy_asset_url_prefix', ''), '/');
        if ($legacy !== '' && $relativeWithinTheme !== '') {
            return asset($legacy.'/'.$relativeWithinTheme);
        }

        return asset($primary);
    }

    private function systemBundledUsesObjectStorage(): bool
    {
        $raw = config('themes.system_theme_use_object_storage');
        if ($raw === null || $raw === '') {
            return ! TenantStorageDisks::usesLocalFlyAdapter(TenantStorageDisks::publicDisk());
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return list<string>
     */
    public function sectionKeys(string $themeKey): array
    {
        return $this->get($this->normalizeKey($themeKey))->sections;
    }

    private function normalizeKey(string $themeKey): string
    {
        $k = strtolower(trim($themeKey));
        if ($k !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $k)) {
            return $k;
        }

        $d = strtolower(trim((string) config('themes.default_key', 'moto')));
        if ($d !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $d)) {
            return $d;
        }

        return 'moto';
    }
}
