<?php

namespace App\Product\CRM\BookingWorkspace;

final readonly class BookingTimelineSegmentData
{
    public const TYPE_REQUESTED = 'requested';

    public const TYPE_CONFIRMED = 'confirmed';

    public const TYPE_PENDING = 'pending';

    public const TYPE_BLOCKED = 'blocked';

    public const TYPE_TODAY_MARKER = 'today_marker';

    public function __construct(
        public string $type,
        public string $startDate,
        public string $endDate,
        public string $label,
        public string $shortLabel,
        public ?int $relatedBookingId,
        public string $statusLabel,
        public bool $isConflicting,
        /** @var list<string> */
        public array $tooltipLines,
        public float $leftPercent,
        public float $widthPercent,
    ) {}
}
