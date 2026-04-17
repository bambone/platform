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
        $settings = $this->ensureSettings($tenant);
        $plan = $tenant->plan;
        $planAllows = $plan !== null && $plan->hasFeature(TenantPushFeature::WEB_PUSH_ONESIGNAL);

        return TenantPushGateResult::fromSettings(
            $settings,
            $this->platformNotificationSettings->isChannelEnabled('web_push_onesignal'),
            $planAllows,
        );
    }
}
