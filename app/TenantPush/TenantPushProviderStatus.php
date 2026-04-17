<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushProviderStatus: string
{
    case NotConfigured = 'not_configured';
    case Invalid = 'invalid';
    case Verified = 'verified';
}
