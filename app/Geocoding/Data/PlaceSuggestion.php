<?php

declare(strict_types=1);

namespace App\Geocoding\Data;

/**
 * Нормализованная подсказка населённого пункта (после разбора ответа провайдера).
 */
final readonly class PlaceSuggestion
{
    public function __construct(
        public string $selectionId,
        public string $displayLabel,
        public string $city,
        public string $region,
        public string $country,
        public string $provider = 'nominatim',
        public ?string $placeKind = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'selectionId' => $this->selectionId,
            'displayLabel' => $this->displayLabel,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'provider' => $this->provider,
            'placeKind' => $this->placeKind,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            selectionId: (string) $data['selectionId'],
            displayLabel: (string) $data['displayLabel'],
            city: (string) $data['city'],
            region: (string) $data['region'],
            country: (string) $data['country'],
            provider: (string) ($data['provider'] ?? 'nominatim'),
            placeKind: isset($data['placeKind']) ? (string) $data['placeKind'] : null,
        );
    }
}
