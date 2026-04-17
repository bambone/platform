<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Models\Tenant;

/**
 * Строка таблицы «Клиенты: Push и PWA» (платформа) — готовые данные для view.
 */
final readonly class PlatformTenantPushTableRow
{
    public function __construct(
        public Tenant $tenant,
        public string $tenantName,
        public string $planSlug,
        public string $editUrl,
        public TenantPushOverride $override,
        public string $overrideLabel,
        public string $overrideBadgeColor,
        public bool $entitled,
        public TenantPushAccessDenialCode $denialCode,
        public string $denialLabel,
        public bool $cabinetCanEdit,
        public ?string $cabinetEditNote,
        public TenantPushProviderStatus $providerStatus,
        public string $providerLabel,
        public string $providerBadgeColor,
        public TenantPushSubscriptionAggregate $subscriptionAggregate,
        public string $subscriptionLabel,
        public string $subscriptionBadgeColor,
        public string $pushCell,
        public string $pwaCell,
    ) {}
}
