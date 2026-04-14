<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

use App\Support\SafeMapPublicUrl;

/**
 * Canonical safe map state for contacts blocks (read-time + shared rules with persistence).
 */
final readonly class ContactMapCanonical
{
    public function __construct(
        public bool $mapEnabled,
        public MapProvider $mapProvider,
        public string $mapPublicUrl,
        /** Дополнительная ссылка на другую карту (кнопка), например 2ГИС при основной Яндекс. */
        public string $mapSecondaryPublicUrl,
        public MapDisplayMode $mapDisplayMode,
        public string $mapTitle,
    ) {}

    public function hasVisibleMap(): bool
    {
        if (! $this->mapEnabled) {
            return false;
        }
        if ($this->mapSecondaryPublicUrl !== '') {
            return true;
        }
        if ($this->mapProvider === MapProvider::None) {
            return false;
        }

        return $this->mapPublicUrl !== '';
    }

    /**
     * Resolve using migration priority; never returns raw HTML.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromDataJson(array $data): self
    {
        $title = trim((string) ($data['map_title'] ?? ''));
        $displayMode = MapDisplayMode::fromDataJson($data);
        $secondary = self::secondaryFromData($data);

        if (array_key_exists('map_enabled', $data) && ! self::truthy($data['map_enabled'])) {
            return new self(
                mapEnabled: false,
                mapProvider: MapProvider::None,
                mapPublicUrl: '',
                mapSecondaryPublicUrl: '',
                mapDisplayMode: $displayMode,
                mapTitle: $title,
            );
        }

        // 1) New contract: map_public_url
        $newUrl = trim((string) ($data['map_public_url'] ?? ''));
        if ($newUrl !== '') {
            $classified = SafeMapPublicUrl::normalizeAndClassify($newUrl);
            if ($classified !== null) {
                [$normalized, $fromUrl] = $classified;
                $declared = MapProvider::tryFromMixed($data['map_provider'] ?? '');
                $provider = self::reconcileProvider($declared, $fromUrl, $normalized);
                $enabled = ! array_key_exists('map_enabled', $data) || self::truthy($data['map_enabled']);
                if ($provider === MapProvider::None) {
                    $enabled = false;
                }

                return new self(
                    mapEnabled: $enabled && $provider !== MapProvider::None,
                    mapProvider: $provider === MapProvider::None ? MapProvider::None : $provider,
                    mapPublicUrl: $provider === MapProvider::None ? '' : $normalized,
                    mapSecondaryPublicUrl: $secondary,
                    mapDisplayMode: $displayMode,
                    mapTitle: $title,
                );
            }

            // Invalid URL: keep editor intent (enabled + declared provider) for hydrate; no public URL.
            $declared = MapProvider::tryFromMixed($data['map_provider'] ?? '');
            $enabled = self::truthy($data['map_enabled'] ?? false);

            return new self(
                mapEnabled: $enabled && $declared !== null && $declared !== MapProvider::None,
                mapProvider: $declared ?? MapProvider::None,
                mapPublicUrl: '',
                mapSecondaryPublicUrl: $secondary,
                mapDisplayMode: $displayMode,
                mapTitle: $title,
            );
        }

        // 2) Legacy link fields
        $legacyLink = trim((string) ($data['map_link'] ?? ''));
        if ($legacyLink === '') {
            $legacyLink = trim((string) ($data['map_url'] ?? ''));
        }
        if ($legacyLink !== '') {
            $classified = SafeMapPublicUrl::normalizeAndClassify($legacyLink);
            if ($classified !== null) {
                [$normalized, $provider] = $classified;

                return new self(
                    mapEnabled: true,
                    mapProvider: $provider,
                    mapPublicUrl: $normalized,
                    mapSecondaryPublicUrl: $secondary,
                    mapDisplayMode: $displayMode,
                    mapTitle: $title,
                );
            }
        }

        // 3) Legacy iframe HTML — only extract src, never expose HTML
        $embedHtml = trim((string) ($data['map_embed'] ?? ''));
        if ($embedHtml === '') {
            $embedHtml = trim((string) ($data['map_embed_html'] ?? ''));
        }
        if ($embedHtml !== '') {
            $src = SafeMapPublicUrl::extractFirstIframeSrc($embedHtml);
            if ($src !== null && $src !== '') {
                $classified = SafeMapPublicUrl::normalizeAndClassify($src);
                if ($classified !== null) {
                    [$normalized, $provider] = $classified;

                    return new self(
                        mapEnabled: true,
                        mapProvider: $provider,
                        mapPublicUrl: $normalized,
                        mapSecondaryPublicUrl: $secondary,
                        mapDisplayMode: $displayMode,
                        mapTitle: $title,
                    );
                }
            }
        }

        $hasPrimaryFromData = trim((string) ($data['map_public_url'] ?? '')) !== ''
            || trim((string) ($data['map_link'] ?? '')) !== ''
            || trim((string) ($data['map_url'] ?? '')) !== ''
            || trim((string) ($data['map_embed'] ?? '')) !== ''
            || trim((string) ($data['map_embed_html'] ?? '')) !== '';

        if (! $hasPrimaryFromData && $secondary !== '' && (! array_key_exists('map_enabled', $data) || self::truthy($data['map_enabled']))) {
            return new self(
                mapEnabled: true,
                mapProvider: MapProvider::None,
                mapPublicUrl: '',
                mapSecondaryPublicUrl: $secondary,
                mapDisplayMode: $displayMode,
                mapTitle: $title,
            );
        }

        // 4) Enabled with empty URL — preserve provider for editor
        $declared = MapProvider::tryFromMixed($data['map_provider'] ?? '');
        $enabled = ! array_key_exists('map_enabled', $data) || self::truthy($data['map_enabled']);
        if ($enabled && $declared !== null && $declared !== MapProvider::None) {
            return new self(
                mapEnabled: true,
                mapProvider: $declared,
                mapPublicUrl: '',
                mapSecondaryPublicUrl: $secondary,
                mapDisplayMode: $displayMode,
                mapTitle: $title,
            );
        }

        return new self(
            mapEnabled: false,
            mapProvider: MapProvider::None,
            mapPublicUrl: '',
            mapSecondaryPublicUrl: $secondary,
            mapDisplayMode: $displayMode,
            mapTitle: $title,
        );
    }

    private static function secondaryFromData(array $data): string
    {
        $paste = '';
        if (array_key_exists('map_secondary_combined_input', $data)) {
            $paste = trim((string) $data['map_secondary_combined_input']);
        }
        if ($paste === '') {
            $paste = trim((string) ($data['map_secondary_public_url'] ?? ''));
        }
        if ($paste === '') {
            return '';
        }
        $parse = ContactMapSourceParser::parse(MapInputMode::Auto, $paste, null);
        if ($parse->isEmpty || ! $parse->ok) {
            return '';
        }
        $classified = SafeMapPublicUrl::normalizeAndClassify($parse->normalizedPublicUrl);

        return $classified !== null ? $classified[0] : '';
    }

    private static function reconcileProvider(?MapProvider $declared, MapProvider $fromUrl, string $normalizedUrl): MapProvider
    {
        if ($declared !== null && $declared !== MapProvider::None && SafeMapPublicUrl::validateMatchesProvider($normalizedUrl, $declared)) {
            return $declared;
        }

        return $fromUrl;
    }

    private static function truthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 1 || $v === '1' || $v === 'true') {
            return true;
        }

        return false;
    }
}
