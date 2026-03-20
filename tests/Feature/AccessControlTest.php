<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeTenant(string $slug = 'acme'): Tenant
    {
        return Tenant::query()->create([
            'name' => 'ACME',
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    public function test_blocked_user_cannot_access_platform_or_tenant_panel(): void
    {
        $tenant = $this->makeTenant();

        $user = User::factory()->create([
            'email' => 'blocked@example.com',
            'status' => 'blocked',
        ]);
        $user->assignRole('platform_owner');
        $user->tenants()->attach($tenant->id, [
            'role' => 'tenant_owner',
            'status' => 'active',
        ]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('platform')));

        app(CurrentTenantManager::class)->setTenant($tenant);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_tenant_only_user_cannot_access_platform_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'tenantonly@example.com',
            'status' => 'active',
        ]);
        $tenant = $this->makeTenant();
        $user->tenants()->attach($tenant->id, [
            'role' => 'tenant_owner',
            'status' => 'active',
        ]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('platform')));
    }

    public function test_platform_only_user_cannot_access_tenant_admin_without_membership(): void
    {
        $user = User::factory()->create([
            'email' => 'platformonly@example.com',
            'status' => 'active',
        ]);
        $user->assignRole('platform_owner');

        $tenant = $this->makeTenant('other');
        app(CurrentTenantManager::class)->setTenant($tenant);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_tenant_owner_can_access_admin_panel_when_tenant_context_set(): void
    {
        $tenant = $this->makeTenant('mototest');
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'status' => 'active',
        ]);
        $user->tenants()->attach($tenant->id, [
            'role' => 'tenant_owner',
            'status' => 'active',
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_platform_owner_can_access_platform_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'po@example.com',
            'status' => 'active',
        ]);
        $user->assignRole('platform_owner');

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('platform')));
    }
}
