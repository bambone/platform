<?php

namespace App\Themes;

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
     * Публичный URL ассета темы: сначала {@see public/themes/{key}}, затем legacy-префикс из конфига.
     */
    public function assetUrl(string $themeKey, string $relativeWithinTheme): string
    {
        $relativeWithinTheme = ltrim($relativeWithinTheme, '/');
        $def = $this->get($this->normalizeKey($themeKey));
        $primary = $def->assetWebPrefix.'/'.$relativeWithinTheme;
        if ($relativeWithinTheme !== '' && is_file(public_path($primary))) {
            return asset($primary);
        }

        $resourceFile = resource_path('themes/'.$def->key.'/public/'.$relativeWithinTheme);
        if ($relativeWithinTheme !== '' && is_file($resourceFile)) {
            return route('theme.platform.asset', ['theme' => $def->key, 'path' => $relativeWithinTheme]);
        }

        $legacy = trim((string) config('themes.legacy_asset_url_prefix', ''), '/');
        if ($legacy !== '' && $relativeWithinTheme !== '') {
            return asset($legacy.'/'.$relativeWithinTheme);
        }

        return asset($primary);
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
