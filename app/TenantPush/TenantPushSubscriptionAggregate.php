<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushSubscriptionAggregate: string
{
    case None = 'none';
    case Partial = 'partial';
    case Active = 'active';
}
