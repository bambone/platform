<?php

declare(strict_types=1);

namespace App\Geocoding\Contracts;

/**
 * Провайдер поиска населённых пунктов (например OpenStreetMap Nominatim).
 *
 * @return list<array{
 *     city: string,
 *     region: string,
 *     country: string,
 *     displayLabel: string,
 *     placeKind: string|null
 * }>
 */
interface GeocodingProviderContract
{
    public function searchPlaces(string $query, int $limit): array;
}
