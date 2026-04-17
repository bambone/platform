<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Models\Tenant;
use App\Models\TenantPushSettings;
use App\Services\Platform\PlatformNotificationSettings;

final class TenantPushFeatureGate
{
    public function __construct(
        private readonly PlatformNotificationSettings $platformNotificationSettings,
    ) {}

    public function findSettings(Tenant $tenant): ?TenantPushSettings
    {
        return TenantPushSettings::query()->where('tenant_id', $tenant->id)->first();
    }

    /**
     * Read-only: из БД или несохранённый шаблон с дефолтами (без INSERT).
     * Используется в {@see evaluate} и отображении.
     */
    public function resolveSettingsForDisplay(Tenant $tenant): TenantPushSettings
    {
        return $this->findSettings($tenant) ?? $this->newUnsavedDefaultSettings($tenant);
    }

    /**
     * Explicit write: создаёт строку при отсутствии (save платформы, save кабинета, CRM и т.д.).
     */
    public function ensureSettings(Tenant $tenant): TenantPushSettings
    {
        return TenantPushSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'push_override' => TenantPushOverride::InheritPlan->value,
                'self_serve_allowed' => true,
                'commercial_service_active' => false,
                'setup_status' => TenantPushSetupStatus::NotStarted->value,
                'provider_status' => TenantPushProviderStatus::NotConfigured->value,
            ],
        );
    }

    public function evaluate(Tenant $tenant): TenantPushGateResult
    {
        $settings = $this->resolveSettingsForDisplay($tenant);
        $plan = $tenant->plan;
        $planAllows = $plan !== null && $plan->hasFeature(TenantPushFeature::WEB_PUSH_ONESIGNAL);

        return TenantPushGateResult::fromSettings(
            $settings,
            $this->platformNotificationSettings->isChannelEnabled('web_push_onesignal'),
            $planAllows,
        );
    }

    private function newUnsavedDefaultSettings(Tenant $tenant): TenantPushSettings
    {
        return new TenantPushSettings([
            'tenant_id' => $tenant->id,
            'push_override' => TenantPushOverride::InheritPlan->value,
            'self_serve_allowed' => true,
            'commercial_service_active' => false,
            'setup_status' => TenantPushSetupStatus::NotStarted->value,
            'provider_status' => TenantPushProviderStatus::NotConfigured->value,
        ]);
    }
}
