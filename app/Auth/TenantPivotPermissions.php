<?php

namespace App\Auth;

/**
 * Maps tenant_user pivot role to Spatie permission names for the current tenant context.
 * Used by Gate::before when serving the tenant Filament panel.
 */
final class TenantPivotPermissions
{
    private const ALL_TENANT = [
        'manage_users',
        'manage_roles',
        'manage_settings',
        'manage_seo',
        'manage_pages',
        'manage_homepage',
        'manage_motorcycles',
        'manage_leads',
        'export_leads',
        'manage_bookings',
        'manage_reviews',
        'manage_faq',
        'manage_contacts',
        'manage_media',
        'manage_integrations',
    ];

    /**
     * @return list<string>
     */
    public static function permissionsForPivotRole(string $role): array
    {
        return match ($role) {
            'tenant_owner', 'tenant_admin' => self::ALL_TENANT,
            'booking_manager' => ['manage_leads', 'export_leads', 'manage_bookings'],
            'fleet_manager' => ['manage_motorcycles', 'manage_integrations'],
            'content_manager' => [
                'manage_pages', 'manage_homepage', 'manage_motorcycles',
                'manage_reviews', 'manage_faq', 'manage_contacts',
                'manage_media', 'manage_seo',
            ],
            'operator' => ['manage_leads'],
            default => [],
        };
    }

    public static function pivotRoleAllows(string $pivotRole, string $permission): bool
    {
        return in_array($permission, self::permissionsForPivotRole($pivotRole), true);
    }
}
