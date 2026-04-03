<?php

namespace App\Tenant\StorageQuota;

final readonly class TenantStorageQuotaData
{
    /**
     * @param  array<string, mixed>|null  $lastScanSummary
     */
    public function __construct(
        public int $tenantId,
        public int $baseQuotaBytes,
        public int $extraQuotaBytes,
        public int $effectiveQuotaBytes,
        public int $usedBytes,
        public int $freeBytes,
        public float $usedPercent,
        public float $freePercent,
        public TenantStorageQuotaStatus $status,
        public bool $hardStopEnabled,
        public ?\DateTimeInterface $lastRecalculatedAt,
        public ?\DateTimeInterface $lastSyncedFromStorageAt,
        public bool $isStaleSync,
        public ?array $lastScanSummary,
        public ?\DateTimeInterface $lastSyncErrorAt,
        public ?string $lastSyncErrorMessage,
        public int $warningThresholdPercent,
        public int $criticalThresholdPercent,
    ) {}

    public function progressBarTier(): string
    {
        if ($this->effectiveQuotaBytes <= 0) {
            return 'exceeded';
        }
        $u = $this->usedPercent;
        if ($u > 100) {
            return 'exceeded';
        }
        if ($u >= 90) {
            return 'danger';
        }
        if ($u >= 80) {
            return 'warning';
        }

        return 'normal';
    }
}
