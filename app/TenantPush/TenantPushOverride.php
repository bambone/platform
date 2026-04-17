<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushOverride: string
{
    case InheritPlan = 'inherit_plan';
    case ForceEnable = 'force_enable';
    case ForceDisable = 'force_disable';

    public function platformLabel(): string
    {
        return match ($this) {
            self::InheritPlan => 'Как в тарифе',
            self::ForceEnable => 'Принудительно включено',
            self::ForceDisable => 'Принудительно выключено',
        };
    }

    public function filamentBadgeColor(): string
    {
        return match ($this) {
            self::InheritPlan => 'gray',
            self::ForceEnable => 'success',
            self::ForceDisable => 'danger',
        };
    }
}
