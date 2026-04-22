<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushStepStatus: string
{
    case NotStarted = 'not_started';
    case Partial = 'partial';
    case Ready = 'ready';
}
