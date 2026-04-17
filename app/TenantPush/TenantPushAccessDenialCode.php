<?php

declare(strict_types=1);

namespace App\TenantPush;

/**
 * Нормализованная причина, почему {@see TenantPushGateResult::isFeatureEntitled()} == false.
 */
enum TenantPushAccessDenialCode: string
{
    case None = 'none';
    case PlatformChannelDisabled = 'platform_channel_disabled';
    case ForceDisabled = 'force_disabled';
    case PlanFeatureMissing = 'plan_feature_missing';
    case CommercialInactive = 'commercial_inactive';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::None => '—',
            self::PlatformChannelDisabled => 'Канал OneSignal выключен на платформе',
            self::ForceDisabled => 'Принудительно выключено платформой',
            self::PlanFeatureMissing => 'В тарифе нет функции Push/PWA',
            self::CommercialInactive => 'Коммерческая активация не включена (платформа)',
            self::Unknown => 'Нет доступа (условия тарифа/платформы)',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::PlatformChannelDisabled => 'danger',
            self::ForceDisabled => 'danger',
            self::PlanFeatureMissing => 'warning',
            self::CommercialInactive => 'warning',
            self::Unknown => 'gray',
        };
    }
}
