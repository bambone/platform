<?php

namespace App\Themes;

/**
 * Манифест платформенной темы (resources/themes/{key}/theme.json).
 */
final readonly class ThemeDefinition
{
    /**
     * @param  list<string>  $sections
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $version,
        public string $assetWebPrefix,
        public array $sections,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $key = strtolower(trim((string) ($data['key'] ?? '')));
        $sections = $data['sections'] ?? [];
        if (! is_array($sections)) {
            $sections = [];
        }

        return new self(
            key: $key !== '' ? $key : 'default',
            name: (string) ($data['name'] ?? $key),
            version: (string) ($data['version'] ?? '0.0.0'),
            assetWebPrefix: trim((string) ($data['asset_web_prefix'] ?? ''), '/'),
            sections: array_values(array_filter(array_map('strval', $sections))),
        );
    }

    public static function synthetic(string $key): self
    {
        $root = trim((string) config('themes.public_asset_root', 'themes'), '/');

        return new self(
            key: $key,
            name: $key,
            version: '0.0.0',
            assetWebPrefix: $root.'/'.$key,
            sections: [],
        );
    }
}
