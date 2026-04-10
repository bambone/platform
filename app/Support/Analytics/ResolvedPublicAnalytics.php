<?php

namespace App\Support\Analytics;

/**
 * Single resolve per HTTP request for public analytics: head and body snippets
 * must use the same decision (no diverging Yandex in head vs noscript in body).
 */
final class ResolvedPublicAnalytics
{
    public function __construct(
        public readonly ?int $yandexCounterId,
        public readonly bool $yandexClickmap,
        public readonly bool $yandexTrackLinks,
        public readonly bool $yandexAccurateTrackBounce,
        public readonly bool $yandexWebvisor,
        public readonly bool $yandexIncludeSsr,
        public readonly bool $yandexIncludeEcommerceDataLayer,
        public readonly ?string $ga4MeasurementId,
    ) {}

    public static function empty(): self
    {
        return new self(
            yandexCounterId: null,
            yandexClickmap: false,
            yandexTrackLinks: false,
            yandexAccurateTrackBounce: false,
            yandexWebvisor: false,
            yandexIncludeSsr: false,
            yandexIncludeEcommerceDataLayer: false,
            ga4MeasurementId: null,
        );
    }

    public function shouldRenderYandex(): bool
    {
        return $this->yandexCounterId !== null;
    }

    public function shouldRenderGa4(): bool
    {
        return $this->ga4MeasurementId !== null && $this->ga4MeasurementId !== '';
    }
}
