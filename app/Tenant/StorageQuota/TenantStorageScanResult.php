<?php

namespace App\Tenant\StorageQuota;

final readonly class TenantStorageScanResult
{
    /**
     * @param  array<string, mixed>  $diskBreakdown
     */
    public function __construct(
        public int $publicBytes,
        public int $privateBytes,
        public int $totalBytes,
        public int $objectCount,
        public \DateTimeInterface $scannedAt,
        public array $diskBreakdown = [],
        public int $brandingBytes = 0,
        public int $mediaBytes = 0,
        public int $seoBytes = 0,
        public int $otherBytes = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSummaryJson(): array
    {
        return [
            'public_bytes' => $this->publicBytes,
            'private_bytes' => $this->privateBytes,
            'total_bytes' => $this->totalBytes,
            'object_count' => $this->objectCount,
            'branding_bytes' => $this->brandingBytes,
            'media_bytes' => $this->mediaBytes,
            'seo_bytes' => $this->seoBytes,
            'other_bytes' => $this->otherBytes,
            'scanned_at' => $this->scannedAt->format(\DateTimeInterface::ATOM),
            'disk_breakdown' => $this->diskBreakdown,
        ];
    }
}
