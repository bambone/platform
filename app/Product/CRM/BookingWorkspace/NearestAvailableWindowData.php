<?php

namespace App\Product\CRM\BookingWorkspace;

final readonly class NearestAvailableWindowData
{
    public function __construct(
        public string $startDate,
        public string $endDate,
        public string $label,
    ) {}
}
