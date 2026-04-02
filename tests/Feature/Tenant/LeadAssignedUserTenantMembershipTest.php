<?php

namespace Tests\Feature\Tenant;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class LeadAssignedUserTenantMembershipTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_lead_rejects_assigned_user_who_is_not_active_tenant_member(): void
    {
        $tenantA = $this->createTenantWithActiveDomain('t-lead-a');
        $tenantB = $this->createTenantWithActiveDomain('t-lead-b');

        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->tenants()->attach($tenantB->id, [
            'role' => 'operator',
            'status' => 'active',
        ]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Test',
            'phone' => '+79990001122',
            'email' => 'x@example.test',
            'source' => 'booking_form',
            'status' => 'new',
        ]);

        $this->expectException(ValidationException::class);
        $lead->assigned_user_id = $outsider->id;
        $lead->save();
    }

    public function test_lead_accepts_assigned_user_with_active_tenant_panel_role(): void
    {
        $tenantA = $this->createTenantWithActiveDomain('t-lead-ok');

        $member = User::factory()->create(['status' => 'active']);
        $member->tenants()->attach($tenantA->id, [
            'role' => 'operator',
            'status' => 'active',
        ]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Test',
            'phone' => '+79990001122',
            'email' => 'ok@example.test',
            'source' => 'booking_form',
            'status' => 'new',
        ]);

        $lead->assigned_user_id = $member->id;
        $lead->save();

        $this->assertSame($member->id, $lead->fresh()->assigned_user_id);
    }

    public function test_lead_rejects_member_with_unknown_pivot_role(): void
    {
        $tenantA = $this->createTenantWithActiveDomain('t-lead-role');

        $weird = User::factory()->create(['status' => 'active']);
        $weird->tenants()->attach($tenantA->id, [
            'role' => 'not_a_real_role',
            'status' => 'active',
        ]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Test',
            'phone' => '+79990001122',
            'email' => 'weird@example.test',
            'source' => 'booking_form',
            'status' => 'new',
        ]);

        $this->expectException(ValidationException::class);
        $lead->assigned_user_id = $weird->id;
        $lead->save();
    }
}
