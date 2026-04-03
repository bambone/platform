<?php

namespace App\Tenant\StorageQuota;

/**
 * Nested withoutQuotaEnforcement scopes for maintenance commands only.
 */
final class TenantStorageQuotaEnforcementContext
{
    private static int $bypassDepth = 0;

    public static function enterBypass(): void
    {
        self::$bypassDepth++;
    }

    public static function leaveBypass(): void
    {
        self::$bypassDepth = max(0, self::$bypassDepth - 1);
    }

    public static function isBypassed(): bool
    {
        return self::$bypassDepth > 0;
    }
}
