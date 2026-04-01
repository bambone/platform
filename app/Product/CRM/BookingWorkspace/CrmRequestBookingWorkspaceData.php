<?php

namespace App\Product\CRM\BookingWorkspace;

/**
 * Единый контракт данных booking-aware блоков CRM workspace для Blade (без логики в шаблоне).
 */
final readonly class CrmRequestBookingWorkspaceData
{
    /**
     * @param  list<string>  $warnings
     * @param  list<BookingTimelineSegmentData>  $timelineSegments
     * @param  list<BookingTimelineAxisTickData>  $timelineAxisTicks
     * @param  list<ConflictingBookingCompactData>  $conflictingBookingsCompact
     * @param  list<string>  $insightLines
     */
    public function __construct(
        public bool $hasBookingContext,
        public string $source,
        public ?int $motorcycleId,
        public string $motorcycleTitle,
        public ?string $motorcycleImageUrl,
        public string $motorcycleDescriptor,
        public ?string $motorcycleStatusLabel,
        public ?string $priceLabel,
        public ?string $requestedStartDate,
        public ?string $requestedEndDate,
        public string $requestedHumanRange,
        public string $requestedDurationLabel,
        public BookingWorkspaceAvailabilityState $availabilityState,
        public string $availabilityStateLabel,
        public string $availabilitySummaryText,
        public string $availabilityBadgeTone,
        public int $conflictsCount,
        public ?NearestAvailableWindowData $nearestAvailableWindow,
        public ?string $timelineWindowStart,
        public ?string $timelineWindowEnd,
        public string $timelineWindowHuman,
        public array $timelineSegments,
        public array $timelineAxisTicks,
        public array $warnings,
        public array $conflictingBookingsCompact,
        public ?string $adminMotorcycleUrl,
        public ?string $adminBookingsIndexUrl,
        public bool $showTimelinePanel,
        public bool $showInsightsPanel,
        public array $insightLines,
    ) {}
}
