<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\TemplatePreset;
use App\Models\Tenant;
use App\Services\Seo\InitializeTenantSeoDefaults;
use App\Services\TemplateCloningService;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use App\TenantPush\TenantPushFeatureGate;

/**
 * Единый bootstrap нового клиента после создания записи {@see Tenant}.
 * Шаги идемпотентны: повторный вызов с теми же данными не должен ломать состояние.
 */
final class TenantProvisioningService
{
    public function bootstrapAfterTenantCreated(Tenant $tenant, ?int $templatePresetId = null): void
    {
        app(TenantStorageQuotaService::class)->ensureQuotaRecord($tenant);

        app(TenantPushFeatureGate::class)->ensureSettings($tenant);

        if ($templatePresetId !== null && $templatePresetId > 0) {
            $preset = TemplatePreset::query()->find($templatePresetId);
            if ($preset !== null) {
                app(TemplateCloningService::class)->cloneToTenant($tenant, $preset);
            }
        }

        app(TenantDomainService::class)->createDefaultSubdomain($tenant, $tenant->slug);

        app(InitializeTenantSeoDefaults::class)->execute($tenant, false, false);
    }
}
