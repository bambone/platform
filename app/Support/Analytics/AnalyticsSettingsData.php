<?php

namespace App\Support\Analytics;

final class AnalyticsSettingsData
{
    public function __construct(
        public readonly bool $yandexEnabled,
        public readonly string $yandexCounterId,
        public readonly bool $yandexWebvisor,
        public readonly bool $yandexClickmap,
        public readonly bool $yandexTrackLinks,
        public readonly bool $yandexAccurateBounce,
        public readonly bool $ga4Enabled,
        public readonly string $ga4MeasurementId,
    ) {}

    public static function defaultEmpty(): self
    {
        return new self(
            yandexEnabled: false,
            yandexCounterId: '',
            yandexWebvisor: false,
            yandexClickmap: false,
            yandexTrackLinks: false,
            yandexAccurateBounce: false,
            ga4Enabled: false,
            ga4MeasurementId: '',
        );
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    public static function fromStorage(?array $json): self
    {
        if ($json === null || $json === []) {
            return self::defaultEmpty();
        }

        $ym = is_array($json['yandex_metrica'] ?? null) ? $json['yandex_metrica'] : [];
        $ga = is_array($json['ga4'] ?? null) ? $json['ga4'] : [];

        $counterRaw = $ym['counter_id'] ?? '';
        $yandexCounterId = is_string($counterRaw)
            ? $counterRaw
            : (is_int($counterRaw) ? (string) $counterRaw : '');

        $measurementRaw = $ga['measurement_id'] ?? '';
        $measurementId = is_string($measurementRaw)
            ? $measurementRaw
            : '';

        return new self(
            yandexEnabled: (bool) ($ym['enabled'] ?? false),
            yandexCounterId: $yandexCounterId,
            yandexWebvisor: (bool) ($ym['webvisor_enabled'] ?? false),
            yandexClickmap: (bool) ($ym['clickmap_enabled'] ?? false),
            yandexTrackLinks: (bool) ($ym['track_links_enabled'] ?? false),
            yandexAccurateBounce: (bool) ($ym['accurate_bounce_enabled'] ?? false),
            ga4Enabled: (bool) ($ga['enabled'] ?? false),
            ga4MeasurementId: $measurementId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        return [
            'yandex_metrica' => [
                'enabled' => $this->yandexEnabled,
                'counter_id' => $this->yandexCounterId,
                'webvisor_enabled' => $this->yandexWebvisor,
                'clickmap_enabled' => $this->yandexClickmap,
                'track_links_enabled' => $this->yandexTrackLinks,
                'accurate_bounce_enabled' => $this->yandexAccurateBounce,
            ],
            'ga4' => [
                'enabled' => $this->ga4Enabled,
                'measurement_id' => $this->ga4MeasurementId,
            ],
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toStorageArray() === $other->toStorageArray();
    }

    /**
     * Any provider flag enabled=true (before re-validation at render time).
     */
    public function hasAnyEnabledProvider(): bool
    {
        return $this->yandexEnabled || $this->ga4Enabled;
    }

    /**
     * No enabled provider with IDs that pass strict validation (post-load / tamper-aware).
     */
    public function isEmpty(): bool
    {
        return ! $this->hasRenderableYandex() && ! $this->hasRenderableGa4();
    }

    public function hasRenderableYandex(): bool
    {
        return $this->yandexEnabled
            && AnalyticsIdValidator::isValidYandexCounterId($this->yandexCounterId);
    }

    public function hasRenderableGa4(): bool
    {
        return $this->ga4Enabled
            && AnalyticsIdValidator::isValidGa4MeasurementId($this->ga4MeasurementId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAuditPayload(): array
    {
        return [
            'yandex_metrica' => [
                'enabled' => $this->yandexEnabled,
                'counter_id' => $this->yandexCounterId,
                'webvisor' => $this->yandexWebvisor,
                'clickmap' => $this->yandexClickmap,
                'track_links' => $this->yandexTrackLinks,
                'accurate_bounce' => $this->yandexAccurateBounce,
            ],
            'ga4' => [
                'enabled' => $this->ga4Enabled,
                'measurement_id' => $this->ga4MeasurementId,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizedForLog(): array
    {
        return $this->toAuditPayload();
    }
}
