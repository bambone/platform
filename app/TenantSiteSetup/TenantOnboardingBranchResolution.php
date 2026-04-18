<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

/**
 * Результат сопоставления желаемой ветки онбординга с capability платформы и правами пользователя.
 */
final readonly class TenantOnboardingBranchResolution
{
    public function __construct(
        public string $desiredBranchId,
        public string $effectiveBranchId,
        public TenantOnboardingBranchConsistency $consistency,
        public TenantOnboardingBlockingReason $blockingReason,
        public TenantOnboardingResolutionAction $resolutionAction,
    ) {}

    public function shouldSuppressBookingAutomation(): bool
    {
        return $this->effectiveBranchId === TenantOnboardingBranchId::CrmOnly->value;
    }

    public function shouldFilterBookingNotificationEvents(): bool
    {
        return $this->shouldSuppressBookingAutomation();
    }

    public function isOk(): bool
    {
        return $this->consistency === TenantOnboardingBranchConsistency::Ok;
    }
}
