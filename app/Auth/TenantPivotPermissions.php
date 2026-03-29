<?php

namespace App\Auth;

use App\Models\PlatformSetting;

/**
 * Maps tenant_user pivot role to permission names for the current tenant context.
 * Used by Gate::before when serving the tenant Filament panel.
 *
 * Overrides: {@see PlatformSetting} key `tenant_pivot_permission_matrix` (JSON: role => string[]).
 */
final class TenantPivotPermissions
{
    private const SETTING_KEY_MATRIX = 'tenant_pivot_permission_matrix';

    /**
     * Default matrix (code): pivot role => list of abilities.
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $all = TenantAbilityRegistry::abilities();

        return [
            'tenant_owner' => $all,
            'tenant_admin' => $all,
            'booking_manager' => ['manage_leads', 'export_leads', 'manage_bookings'],
            'fleet_manager' => ['manage_motorcycles', 'manage_integrations'],
            'content_manager' => [
                'manage_pages', 'manage_homepage', 'manage_motorcycles',
                'manage_reviews', 'manage_faq', 'manage_contacts',
                'manage_media', 'manage_seo',
            ],
            'operator' => ['manage_leads'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsForPivotRole(string $role): array
    {
        $defaults = self::defaults()[$role] ?? [];

        $matrix = PlatformSetting::get(self::SETTING_KEY_MATRIX, null);
        if (! is_array($matrix)) {
            return $defaults;
        }

        $raw = $matrix[$role] ?? null;
        if (! is_array($raw)) {
            return $defaults;
        }

        $filtered = TenantAbilityRegistry::onlyRegistered($raw);
        if ($filtered === []) {
            return $defaults;
        }

        return $filtered;
    }

    public static function pivotRoleAllows(string $pivotRole, string $permission): bool
    {
        return in_array($permission, self::permissionsForPivotRole($pivotRole), true);
    }
}
