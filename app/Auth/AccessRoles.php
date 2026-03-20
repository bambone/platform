<?php

namespace App\Auth;

final class AccessRoles
{
    public const PLATFORM = [
        'platform_owner',
        'platform_admin',
        'support_manager',
    ];

    public const TENANT_MEMBERSHIP = [
        'tenant_owner',
        'tenant_admin',
        'booking_manager',
        'fleet_manager',
        'content_manager',
        'operator',
    ];

    /** @deprecated Use AccessRoles::PLATFORM after migration */
    public const LEGACY_SUPER_ADMIN = 'super_admin';

    public static function platformRoles(): array
    {
        return self::PLATFORM;
    }

    public static function tenantMembershipRolesForPanel(): array
    {
        return self::TENANT_MEMBERSHIP;
    }
}
