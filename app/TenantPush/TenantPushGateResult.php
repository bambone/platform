<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Models\TenantPushSettings;

final readonly class TenantPushGateResult
{
    public function __construct(
        public bool $platformChannelEnabled,
        public bool $planAllowsFeature,
        public bool $overrideForceDisable,
        public bool $overrideForceEnable,
        public bool $commercialActive,
        public bool $selfServeAllowed,
        public bool $canViewSection,
        public bool $canEditSettings,
    ) {}

    public function isFeatureEntitled(): bool
    {
        if ($this->overrideForceDisable) {
            return false;
        }
        if ($this->overrideForceEnable) {
            return $this->platformChannelEnabled;
        }

        return $this->platformChannelEnabled && $this->planAllowsFeature && $this->commercialActive;
    }

    public static function fromSettings(
        TenantPushSettings $settings,
        bool $platformChannelEnabled,
        bool $planAllowsFeature,
    ): self {
        $override = $settings->pushOverrideEnum();
        $forceDisable = $override === TenantPushOverride::ForceDisable;
        $forceEnable = $override === TenantPushOverride::ForceEnable;

        $entitled = self::computeEntitled(
            $platformChannelEnabled,
            $planAllowsFeature,
            $forceDisable,
            $forceEnable,
            (bool) $settings->commercial_service_active,
        );

        $selfServe = (bool) $settings->self_serve_allowed;
        $canEdit = $entitled && ($selfServe || $forceEnable);

        return new self(
            platformChannelEnabled: $platformChannelEnabled,
            planAllowsFeature: $planAllowsFeature,
            overrideForceDisable: $forceDisable,
            overrideForceEnable: $forceEnable,
            commercialActive: (bool) $settings->commercial_service_active,
            selfServeAllowed: $selfServe,
            canViewSection: $platformChannelEnabled,
            canEditSettings: $canEdit,
        );
    }

    private static function computeEntitled(
        bool $platformChannelEnabled,
        bool $planAllowsFeature,
        bool $forceDisable,
        bool $forceEnable,
        bool $commercialActive,
    ): bool {
        if (! $platformChannelEnabled) {
            return false;
        }
        if ($forceDisable) {
            return false;
        }
        if ($forceEnable) {
            return true;
        }

        return $planAllowsFeature && $commercialActive;
    }
}
