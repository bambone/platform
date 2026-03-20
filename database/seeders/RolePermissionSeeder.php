<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Spatie roles and permissions.
 *
 * Legacy roles `super_admin`, `admin`, `manager` remain in the database for compatibility
 * but are not used by `User::canAccessPanel()` — use `platform_*` and `tenant_user` pivot.
 */
class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
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

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $guard = 'web';

        foreach (['platform_owner', 'platform_admin', 'support_manager'] as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
            $role->syncPermissions([]);
        }

        Role::firstOrCreate(['name' => 'tenant_owner', 'guard_name' => $guard])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'tenant_admin', 'guard_name' => $guard])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'booking_manager', 'guard_name' => $guard])
            ->syncPermissions(['manage_leads', 'export_leads', 'manage_bookings']);

        Role::firstOrCreate(['name' => 'fleet_manager', 'guard_name' => $guard])
            ->syncPermissions(['manage_motorcycles', 'manage_integrations']);

        Role::firstOrCreate(['name' => 'content_manager', 'guard_name' => $guard])
            ->syncPermissions([
                'manage_pages', 'manage_homepage', 'manage_motorcycles',
                'manage_reviews', 'manage_faq', 'manage_contacts',
                'manage_media', 'manage_seo',
            ]);

        Role::firstOrCreate(['name' => 'operator', 'guard_name' => $guard])
            ->syncPermissions(['manage_leads']);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard])
            ->syncPermissions([]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => $guard])
            ->syncPermissions(['manage_leads', 'export_leads', 'manage_bookings']);
    }
}
