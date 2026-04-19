<?php

declare(strict_types=1);

namespace App\Geocoding;

use App\Geocoding\Contracts\GeocodingProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Клиент Nominatim (OpenStreetMap). Запросы только с backend.
 *
 * @see https://nominatim.org/release-docs/latest/api/Search/
 */
final class NominatimGeocodingProvider implements GeocodingProviderContract
{
    private const ALLOWED_TYPES = ['city', 'town', 'village'];

    public function __construct(
        private string $baseUrl,
        private string $contactIdentifier,
        private int $timeoutSeconds = 8,
    ) {}

    public function searchPlaces(string $query, int $limit): array
    {
        $url = rtrim($this->baseUrl, '/').'/search';

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'User-Agent' => 'RentBase/1.0 ('.$this->contactIdentifier.')',
                    'Accept-Language' => 'ru,en',
                ])
                ->get($url, [
                    'q' => $query,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => $limit,
                ]);
        } catch (Throwable $e) {
            Log::debug('nominatim_request_failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('nominatim_http_error', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return [];
        }

        /** @var mixed $decoded */
        $decoded = $response->json();
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped = $this->mapItem($item);
            if ($mapped === null) {
                continue;
            }

            $dedupeKey = mb_strtolower($mapped['displayLabel'].'|'.$mapped['city'].'|'.$mapped['region'].'|'.$mapped['country']);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $out[] = $mapped;

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{city: string, region: string, country: string, displayLabel: string, placeKind: string|null}|null
     */
    private function mapItem(array $item): ?array
    {
        $type = strtolower((string) ($item['type'] ?? ''));
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];

        $city = $this->firstNonEmptyString($address, ['city', 'town', 'village']);
        if ($city === null || $city === '') {
            return null;
        }

        $region = $this->firstNonEmptyString($address, ['state', 'region', 'state_district']) ?? '';
        $country = $this->firstNonEmptyString($address, ['country']) ?? '';

        if ($country === '') {
            return null;
        }

        $display = isset($item['display_name']) && is_string($item['display_name'])
            ? trim($item['display_name'])
            : $this->buildDisplayLabel($city, $region, $country);

        if ($display === '') {
            return null;
        }

        return [
            'city' => $city,
            'region' => $region,
            'country' => $country,
            'displayLabel' => $display,
            'placeKind' => $type,
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private function firstNonEmptyString(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            $v = $address[$key] ?? null;
            if (is_string($v)) {
                $t = trim($v);

                return $t !== '' ? $t : null;
            }
        }

        return null;
    }

    private function buildDisplayLabel(string $city, string $region, string $country): string
    {
        $parts = array_filter([$city, $region, $country], fn (string $p): bool => $p !== '');

        return implode(', ', $parts);
    }
}
