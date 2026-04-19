<?php

declare(strict_types=1);

namespace App\Geocoding;

use App\Geocoding\Contracts\GeocodingProviderContract;
use App\Geocoding\Data\PlaceSuggestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Поиск населённых пунктов: кэш по нормализованной строке, маппинг в DTO, хранение выбора по selectionId.
 */
final class GeocodePlacesService
{
    private const MIN_QUERY_LENGTH = 2;

    private const RESULT_LIMIT = 8;

    private const SEARCH_CACHE_PREFIX = 'geocode:places:v2:';

    private const PICK_CACHE_PREFIX = 'geocode:pick:v2:';

    public function __construct(
        private GeocodingProviderContract $provider,
    ) {}

    /**
     * @return list<PlaceSuggestion>
     */
    public function search(string $rawQuery): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $trimmed = $this->trimQuery($rawQuery);
        if (mb_strlen($trimmed) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $cacheKey = mb_strtolower($trimmed);

        try {
            return Cache::remember(
                self::SEARCH_CACHE_PREFIX.hash('sha256', $cacheKey),
                $this->searchTtlSeconds(),
                function () use ($trimmed): array {
                    $rows = $this->provider->searchPlaces($trimmed, self::RESULT_LIMIT);
                    $suggestions = [];
                    foreach ($rows as $row) {
                        $id = (string) Str::uuid();
                        $suggestion = new PlaceSuggestion(
                            selectionId: $id,
                            displayLabel: $row['displayLabel'],
                            city: $row['city'],
                            region: $row['region'],
                            country: $row['country'],
                            provider: 'nominatim',
                            placeKind: $row['placeKind'],
                        );
                        Cache::put(
                            self::PICK_CACHE_PREFIX.$id,
                            $suggestion->toArray(),
                            $this->pickTtlSeconds(),
                        );
                        $suggestions[] = $suggestion;
                    }

                    return $suggestions;
                },
            );
        } catch (Throwable $e) {
            Log::warning('geocode_search_failed', [
                'message' => $e->getMessage(),
                'query' => $rawQuery,
            ]);

            return [];
        }
    }

    /**
     * Восстановить DTO выбранной подсказки по идентификатору (из кэша выбора).
     */
    public function resolvePick(?string $selectionId): ?PlaceSuggestion
    {
        if ($selectionId === null || $selectionId === '') {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = Cache::get(self::PICK_CACHE_PREFIX.$selectionId);
        if (! is_array($data)) {
            return null;
        }

        try {
            return PlaceSuggestion::fromArray($data);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string> selectionId => displayLabel
     */
    public function searchOptions(string $rawQuery): array
    {
        $options = [];
        foreach ($this->search($rawQuery) as $suggestion) {
            $options[$suggestion->selectionId] = $suggestion->displayLabel;
        }

        return $options;
    }

    private function trimQuery(string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.nominatim.enabled', true);
    }

    private function searchTtlSeconds(): int
    {
        return (int) config('services.nominatim.search_cache_ttl', 86400);
    }

    private function pickTtlSeconds(): int
    {
        return (int) config('services.nominatim.pick_cache_ttl', 86400);
    }
}
