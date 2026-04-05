<?php

namespace App\Services\Seo\Data;

/**
 * Snapshot of tenant public SEO inputs for autopilot (read-only; built from DB/settings).
 */
final readonly class TenantSeoBootstrapData
{
    public function __construct(
        public int $tenantId,
        public string $siteName,
        public string $primaryPublicBaseUrl,
        public ?string $themeKey,
        public ?string $localizationPresetSlug,
        public string $locale,
        public string $tenantCurrency,
        public bool $hasReliableTenantCurrency,
        public bool $hasPublishedHomePage,
        public int $catalogItemsCount,
        public int $faqPublishedCount,
        public bool $hasContactPhone,
        public bool $hasContactEmail,
        public ?string $representativeOgImageUrl,
    ) {}
}
