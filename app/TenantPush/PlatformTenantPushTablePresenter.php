<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Filament\Platform\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class PlatformTenantPushTablePresenter
{
    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return list<PlatformTenantPushTableRow>
     */
    public static function rowsForTenants(Collection $tenants, TenantPushFeatureGate $featureGate, TenantPushCrmRequestRecipientResolver $recipientResolver): array
    {
        $rows = [];
        foreach ($tenants as $tenant) {
            $rows[] = self::row($tenant, $featureGate, $recipientResolver);
        }

        return $rows;
    }

    public static function row(
        Tenant $tenant,
        TenantPushFeatureGate $featureGate,
        TenantPushCrmRequestRecipientResolver $recipientResolver,
    ): PlatformTenantPushTableRow {
        $pushView = TenantPushSettingsView::make($tenant, $featureGate, $recipientResolver);
        $gate = $pushView->gate;
        $settings = $pushView->settings;
        $entitled = $gate->isFeatureEntitled();
        $denial = $gate->entitlementDenialCode();
        $override = TenantPushOverride::tryFrom((string) $settings->push_override) ?? TenantPushOverride::InheritPlan;

        $pushCell = $entitled ? ($settings->is_push_enabled ? 'да' : 'выкл') : '—';
        $pwaCell = $entitled ? ($settings->is_pwa_enabled ? 'да' : 'выкл') : '—';

        return new PlatformTenantPushTableRow(
            tenant: $tenant,
            tenantName: (string) $tenant->name,
            planSlug: $tenant->plan?->slug ?? '—',
            editUrl: TenantResource::getUrl('edit', ['record' => $tenant]),
            override: $override,
            overrideLabel: $override->platformLabel(),
            overrideBadgeColor: $override->filamentBadgeColor(),
            entitled: $entitled,
            denialCode: $denial,
            denialLabel: $denial->label(),
            providerStatus: $settings->providerStatusEnum(),
            providerLabel: $settings->providerStatusEnum()->platformLabel(),
            providerBadgeColor: $settings->providerStatusEnum()->filamentBadgeColor(),
            subscriptionAggregate: $pushView->subscriptionAggregate,
            subscriptionLabel: $pushView->subscriptionAggregate->platformLabel(),
            subscriptionBadgeColor: $pushView->subscriptionAggregate->filamentBadgeColor(),
            pushCell: $pushCell,
            pwaCell: $pwaCell,
        );
    }
}
