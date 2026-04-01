<?php

namespace App\Product\CRM\BookingWorkspace;

/**
 * Метка дня на оси таймлайна доступности (готовые проценты для CSS left).
 */
final readonly class BookingTimelineAxisTickData
{
    public function __construct(
        public float $leftPercent,
        public string $label,
        public string $isoDate,
        public bool $isMajor,
    ) {}
}
