<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

use App\Support\SafeMapPublicUrl;

/**
 * Persist-time: canonical map_* keys, strip legacy and editor-only paste fields.
 * Defense in depth: invalid input is stripped even if UI is bypassed.
 */
final class ContactsMapPersistence
{
    /**
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public function finalize(array $dataJson): array
    {
        if (array_key_exists('map_enabled', $dataJson) && ! $this->toBool($dataJson['map_enabled'])) {
            return $this->applyFullyDisabledMap($dataJson);
        }

        $displayMode = MapDisplayMode::fromDataJson($dataJson);
        $title = trim((string) ($dataJson['map_title'] ?? ''));
        $declared = MapProvider::tryFromMixed($dataJson['map_provider'] ?? '');

        $parse = ContactMapSourceParser::parseFromDataJson($dataJson);
        $secondaryNormalized = $this->finalizeSecondaryNormalized($dataJson);

        if ($parse->isEmpty) {
            if ($secondaryNormalized !== '') {
                $dataJson['map_enabled'] = true;
                $dataJson['map_public_url'] = '';
                $dataJson['map_provider'] = MapProvider::None->value;
                $dataJson['map_secondary_public_url'] = $secondaryNormalized;
                $dataJson['map_display_mode'] = $displayMode->value;
                $dataJson['map_title'] = $title !== '' ? mb_substr($title, 0, 255) : '';
                unset($dataJson['map_source_kind']);
                $this->stripTransientMapFields($dataJson);
                $this->stripLegacy($dataJson);

                return $dataJson;
            }
            $dataJson['map_enabled'] = true;
            $dataJson['map_public_url'] = '';
            $dataJson['map_secondary_public_url'] = '';
            $provider = ($declared !== null && $declared !== MapProvider::None)
                ? $declared
                : MapProvider::Yandex;
            $dataJson['map_provider'] = $provider->value;
            $dataJson['map_display_mode'] = $displayMode->value;
            $dataJson['map_title'] = $title !== '' ? mb_substr($title, 0, 255) : '';
            unset($dataJson['map_source_kind']);
            $this->stripTransientMapFields($dataJson);
            $this->stripLegacy($dataJson);

            return $dataJson;
        }

        if (! $parse->ok) {
            $dataJson['map_enabled'] = false;
            $dataJson['map_provider'] = MapProvider::None->value;
            $dataJson['map_public_url'] = '';
            $dataJson['map_secondary_public_url'] = '';
            $dataJson['map_display_mode'] = $displayMode->value;
            $dataJson['map_title'] = $title !== '' ? mb_substr($title, 0, 255) : '';
            unset($dataJson['map_source_kind']);
            $this->stripTransientMapFields($dataJson);
            $this->stripLegacy($dataJson);

            return $dataJson;
        }

        $normalized = $parse->normalizedPublicUrl;
        $classified = SafeMapPublicUrl::normalizeAndClassify($normalized);
        if ($classified === null) {
            $dataJson['map_enabled'] = false;
            $dataJson['map_provider'] = MapProvider::None->value;
            $dataJson['map_public_url'] = '';
            $dataJson['map_secondary_public_url'] = '';
            $dataJson['map_display_mode'] = $displayMode->value;
            $dataJson['map_title'] = $title !== '' ? mb_substr($title, 0, 255) : '';
            unset($dataJson['map_source_kind']);
            $this->stripTransientMapFields($dataJson);
            $this->stripLegacy($dataJson);

            return $dataJson;
        }
        [$storedUrl, $fromUrl] = $classified;
        $finalProvider = $this->reconcileProvider($declared, $fromUrl, $storedUrl);
        if ($finalProvider === MapProvider::None) {
            $dataJson['map_enabled'] = false;
            $dataJson['map_provider'] = MapProvider::None->value;
            $dataJson['map_public_url'] = '';
        } else {
            $dataJson['map_enabled'] = true;
            $dataJson['map_provider'] = $finalProvider->value;
            $dataJson['map_public_url'] = $storedUrl;
        }
        $dataJson['map_display_mode'] = $displayMode->value;
        $dataJson['map_title'] = $title !== '' ? mb_substr($title, 0, 255) : '';
        if ($finalProvider !== MapProvider::None && $dataJson['map_public_url'] !== '' && $parse->sourceKind !== null) {
            $dataJson['map_source_kind'] = $parse->sourceKind->value;
        } else {
            unset($dataJson['map_source_kind']);
        }

        if ($secondaryNormalized !== '' && $secondaryNormalized === (string) ($dataJson['map_public_url'] ?? '')) {
            $secondaryNormalized = '';
        }
        $dataJson['map_secondary_public_url'] = $secondaryNormalized;

        $this->stripTransientMapFields($dataJson);
        $this->stripLegacy($dataJson);

        return $dataJson;
    }

    /**
     * @param  array<string, mixed>  $dataJson
     */
    private function finalizeSecondaryNormalized(array $dataJson): string
    {
        $secRaw = ContactMapSourceParser::rawSecondaryCombinedInput($dataJson);
        if ($secRaw === '') {
            return '';
        }
        $parseSec = ContactMapSourceParser::parse(MapInputMode::Auto, $secRaw, null);
        if ($parseSec->isEmpty || ! $parseSec->ok) {
            return '';
        }
        $classified = SafeMapPublicUrl::normalizeAndClassify($parseSec->normalizedPublicUrl);

        return $classified !== null ? $classified[0] : '';
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    private function applyFullyDisabledMap(array $dataJson): array
    {
        $title = trim((string) ($dataJson['map_title'] ?? ''));
        $displayMode = MapDisplayMode::fromDataJson($dataJson);

        $dataJson['map_enabled'] = false;
        $dataJson['map_provider'] = MapProvider::None->value;
        $dataJson['map_public_url'] = '';
        $dataJson['map_secondary_public_url'] = '';
        $dataJson['map_display_mode'] = $displayMode->value;
        $dataJson['map_title'] = $title !== '' ? mb_substr($title, 0, 255) : '';
        unset($dataJson['map_source_kind']);

        $this->stripTransientMapFields($dataJson);
        $this->stripLegacy($dataJson);

        return $dataJson;
    }

    /**
     * Editor-only keys (never persist raw HTML / combined buffer).
     *
     * @param  array<string, mixed>  $dataJson
     */
    private function stripTransientMapFields(array &$dataJson): void
    {
        foreach ([
            'map_combined_input',
            'map_secondary_combined_input',
            'map_iframe_snippet',
            'map_paste_raw',
            'map_html',
            'map_raw_iframe',
        ] as $k) {
            unset($dataJson[$k]);
        }
    }

    /**
     * @param  array<string, mixed>  $dataJson
     */
    private function stripLegacy(array &$dataJson): void
    {
        unset($dataJson['map_embed_mode']);
        foreach (['map_embed', 'map_link', 'map_embed_html', 'map_url'] as $legacy) {
            unset($dataJson[$legacy]);
        }
    }

    private function reconcileProvider(?MapProvider $declared, MapProvider $fromUrl, string $normalizedUrl): MapProvider
    {
        if ($declared !== null && $declared !== MapProvider::None && SafeMapPublicUrl::validateMatchesProvider($normalizedUrl, $declared)) {
            return $declared;
        }

        return $fromUrl;
    }

    private function toBool(mixed $v): bool
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
