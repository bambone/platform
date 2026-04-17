<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushSetupStatus: string
{
    case NotStarted = 'not_started';
    case PwaIncomplete = 'pwa_incomplete';
    case OnesignalIncomplete = 'onesignal_incomplete';
    case Ready = 'ready';
    case Error = 'error';
}
