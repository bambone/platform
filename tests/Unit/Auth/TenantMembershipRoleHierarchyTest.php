<?php

namespace Tests\Unit\Auth;

use App\Auth\TenantMembershipRoleHierarchy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMembershipRoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_roles_strictly_below_owner(): void
    {
        $keys = TenantMembershipRoleHierarchy::creatableRoleKeys('tenant_owner');
        $this->assertContains('tenant_admin', $keys);
        $this->assertNotContains('tenant_owner', $keys);
    }

    public function test_admin_can_create_only_below_admin(): void
    {
        $keys = TenantMembershipRoleHierarchy::creatableRoleKeys('tenant_admin');
        $this->assertContains('operator', $keys);
        $this->assertNotContains('tenant_admin', $keys);
        $this->assertNotContains('tenant_owner', $keys);
    }

    public function test_admin_cannot_edit_peer_or_owner(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 't',
            'status' => 'active',
        ]);

        $admin = User::factory()->create();
        $admin2 = User::factory()->create();
        $owner = User::factory()->create();
        $operator = User::factory()->create();

        $admin->tenants()->attach($tenant->id, ['role' => 'tenant_admin', 'status' => 'active']);
        $admin2->tenants()->attach($tenant->id, ['role' => 'tenant_admin', 'status' => 'active']);
        $owner->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $operator->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        $this->assertTrue(TenantMembershipRoleHierarchy::canEditTeamMember($admin, $admin, $tenant->id));
        $this->assertFalse(TenantMembershipRoleHierarchy::canEditTeamMember($admin, $admin2, $tenant->id));
        $this->assertFalse(TenantMembershipRoleHierarchy::canEditTeamMember($admin, $owner, $tenant->id));
        $this->assertTrue(TenantMembershipRoleHierarchy::canEditTeamMember($admin, $operator, $tenant->id));
    }

    public function test_owner_can_edit_other_owner(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T2',
            'slug' => 't2',
            'status' => 'active',
        ]);

        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();
        $owner1->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $owner2->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->assertTrue(TenantMembershipRoleHierarchy::canEditTeamMember($owner1, $owner2, $tenant->id));
    }
}
