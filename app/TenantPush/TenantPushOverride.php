<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushOverride: string
{
    case InheritPlan = 'inherit_plan';
    case ForceEnable = 'force_enable';
    case ForceDisable = 'force_disable';
}
