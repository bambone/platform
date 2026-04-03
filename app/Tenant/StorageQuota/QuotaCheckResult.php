<?php

namespace App\Tenant\StorageQuota;

final readonly class QuotaCheckResult
{
    public function __construct(
        public bool $allowed,
        public ?string $reason,
        public bool $wouldExceed,
        public int $usedBytes,
        public int $quotaBytes,
        public int $freeBytes,
        public float $freePercent,
    ) {}
}
