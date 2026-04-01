<?php

namespace App\Product\CRM\BookingWorkspace;

final readonly class ConflictingBookingCompactData
{
    public function __construct(
        public int $id,
        public string $bookingNumber,
        public string $customerLabel,
        public string $dateRangeLabel,
        public string $statusLabel,
        public ?string $viewUrl,
    ) {}
}
