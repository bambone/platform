<?php

namespace Tests\Feature;

use App\Auth\TenantPivotPermissions;
use App\Filament\Platform\Pages\TenantCabinetRoleMatrixPage;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantPivotPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_platform_setting_overrides_default_pivot_permissions(): void
    {
        PlatformSetting::set('tenant_pivot_permission_matrix', [
            'booking_manager' => ['manage_leads'],
        ], 'json');

        $perms = TenantPivotPermissions::permissionsForPivotRole('booking_manager');
        $this->assertSame(['manage_leads'], $perms);
        $this->assertFalse(TenantPivotPermissions::pivotRoleAllows('booking_manager', 'manage_bookings'));
    }

    public function test_empty_saved_list_falls_back_to_code_defaults(): void
    {
        PlatformSetting::set('tenant_pivot_permission_matrix', [
            'booking_manager' => [],
        ], 'json');

        $perms = TenantPivotPermissions::permissionsForPivotRole('booking_manager');
        $this->assertContains('manage_bookings', $perms);
    }

    public function test_unknown_abilities_are_stripped_from_saved_matrix(): void
    {
        PlatformSetting::set('tenant_pivot_permission_matrix', [
            'operator' => ['manage_leads', 'fake_ability'],
        ], 'json');

        $perms = TenantPivotPermissions::permissionsForPivotRole('operator');
        $this->assertSame(['manage_leads'], $perms);
    }

    public function test_gate_uses_matrix_in_admin_panel_context(): void
    {
        PlatformSetting::set('tenant_pivot_permission_matrix', [
            'operator' => ['manage_leads'],
        ], 'json');

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 't',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, [
            'role' => 'operator',
            'status' => 'active',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        $this->assertTrue(Gate::forUser($user)->allows('manage_leads'));
        $this->assertFalse(Gate::forUser($user)->allows('manage_motorcycles'));

        Filament::setCurrentPanel(null);
        app(CurrentTenantManager::class)->setTenant(null);
    }

    public function test_tenant_user_attach_active_membership_allows_expected_ability(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T2',
            'slug' => 't2',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['status' => 'active']);
        $tenant->users()->attach($user->id, [
            'role' => 'fleet_manager',
            'status' => 'active',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        $this->assertTrue(Gate::forUser($user)->allows('manage_motorcycles'));

        Filament::setCurrentPanel(null);
        app(CurrentTenantManager::class)->setTenant(null);
    }

    public function test_matrix_settings_page_accessible_only_to_platform_owner_and_admin(): void
    {
        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole('support_manager');
        $this->actingAs($support);
        $this->assertFalse(TenantCabinetRoleMatrixPage::canAccess());

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('platform_admin');
        $this->actingAs($admin);
        $this->assertTrue(TenantCabinetRoleMatrixPage::canAccess());
    }
}
