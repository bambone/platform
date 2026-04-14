<?php

namespace App\PageBuilder\Contacts;

/**
 * Hydrate editor state from legacy; normalize persistence (sort_order, strip legacy, clean url override).
 */
final class ContactsInfoDataService
{
    public function __construct(
        private readonly ContactChannelsResolver $resolver,
        private readonly ContactsMapPersistence $mapPersistence,
    ) {}

    /**
     * Merge defaults with stored JSON. The `channels` list is never merged recursively row-by-row
     * into `[]`, otherwise a broken or nested structure can collapse into a single repeater item
     * and Filament/Livewire break (add/sort UUID keys, label/state desync).
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public static function mergeDataJsonPreservingChannelList(array $defaults, array $existing): array
    {
        if (! array_key_exists('channels', $defaults)) {
            return array_replace_recursive($defaults, $existing);
        }

        $defaultChannels = $defaults['channels'];
        unset($defaults['channels']);
        $existingChannels = (array_key_exists('channels', $existing) && is_array($existing['channels']))
            ? $existing['channels']
            : $defaultChannels;
        unset($existing['channels']);

        $merged = array_replace_recursive($defaults, $existing);
        $merged['channels'] = self::normalizeChannelsForRepeater($existingChannels);

        return $merged;
    }

    /**
     * Flatten repeater state to a list of channel row arrays (unwrap mistaken single-wrapper shape).
     *
     * @param  mixed  $channels
     * @return list<array<string, mixed>>
     */
    public static function normalizeChannelsForRepeater(mixed $channels): array
    {
        if (! is_array($channels) || $channels === []) {
            return [];
        }

        if (count($channels) === 1) {
            $only = reset($channels);
            if (is_array($only) && $only !== []) {
                $childArrays = array_filter($only, static fn ($v): bool => is_array($v));
                if ($childArrays !== [] && count($childArrays) === count($only)) {
                    return self::normalizeChannelsForRepeater(array_values($childArrays));
                }
            }
        }

        $out = [];
        foreach ($channels as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public function hydrateForEditor(array $dataJson): array
    {
        if ($this->resolver->hasUsableStoredChannels($dataJson)) {
            return $dataJson;
        }

        $channels = $dataJson['channels'] ?? [];
        $hasStored = is_array($channels) && $channels !== [];
        if ($hasStored && ! $this->allChannelsDisabled($channels)) {
            // Есть включённые строки, но они пока неusable — редактор показывает то, что ввели.
            return $dataJson;
        }

        $syn = $this->resolver->legacySyntheticRows($dataJson);
        if ($syn !== []) {
            $dataJson['channels'] = $syn;
        }

        return $dataJson;
    }

    /**
     * @param  list<mixed>  $channels
     */
    private function allChannelsDisabled(array $channels): bool
    {
        foreach ($channels as $row) {
            if (! is_array($row)) {
                continue;
            }
            $v = $row['is_enabled'] ?? true;
            if ($v === true || $v === 1 || $v === '1' || $v === 'true') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public function finalizeForPersistence(array $dataJson): array
    {
        $ch = $dataJson['channels'] ?? null;
        if (is_array($ch)) {
            $out = [];
            foreach (array_values($ch) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $row['sort_order'] = $i;
                $overrideOn = $row['is_override_url'] ?? false;
                $overrideOn = $overrideOn === true || $overrideOn === 1 || $overrideOn === '1' || $overrideOn === 'true';
                if (! $overrideOn) {
                    $row['url'] = null;
                    $row['is_override_url'] = false;
                }
                $tab = $row['open_in_new_tab'] ?? null;
                if ($tab === 'inherit' || $tab === null || $tab === '') {
                    unset($row['open_in_new_tab']);
                }
                $out[] = $row;
            }
            $dataJson['channels'] = $out;
        }

        if ($this->resolver->hasUsableStoredChannels($dataJson)) {
            foreach (['phone', 'email', 'whatsapp', 'telegram'] as $k) {
                $dataJson[$k] = null;
            }
        }

        return $this->mapPersistence->finalize($dataJson);
    }

    /**
     * Editor: show migrated map_* when DB still has legacy keys only.
     *
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public function hydrateMapForEditor(array $dataJson): array
    {
        $c = ContactMapCanonical::fromDataJson($dataJson);
        $dataJson['map_enabled'] = $c->mapEnabled;
        $dataJson['map_provider'] = $c->mapProvider->value;
        $dataJson['map_public_url'] = $c->mapPublicUrl;
        $dataJson['map_combined_input'] = $c->mapPublicUrl;
        $dataJson['map_secondary_combined_input'] = $c->mapSecondaryPublicUrl;
        if (! isset($dataJson['map_input_mode']) || $dataJson['map_input_mode'] === '') {
            $dataJson['map_input_mode'] = MapInputMode::Auto->value;
        }
        $dataJson['map_display_mode'] = $c->mapDisplayMode->value;
        unset($dataJson['map_embed_mode']);

        return $dataJson;
    }
}
