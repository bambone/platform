<?php

declare(strict_types=1);

namespace App\TenantPush;

final class OneSignalExternalUserId
{
    public static function format(int $tenantId, int $userId): string
    {
        return 't'.$tenantId.'_u'.$userId;
    }

    public static function parse(string $value): ?array
    {
        if (preg_match('/^t(\d+)_u(\d+)$/', $value, $m) !== 1) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }
}
