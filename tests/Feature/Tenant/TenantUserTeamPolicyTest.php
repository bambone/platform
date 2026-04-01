<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use App\Tenant\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantUserTeamPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    private function bindTenantPanel(Tenant $tenant): void
    {
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_tenant_admin_cannot_update_owner_or_peer_admin(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Team T',
            'slug' => 'teamt',
            'status' => 'active',
        ]);
        $this->bindTenantPanel($tenant);

        $admin = User::factory()->create(['status' => 'active']);
        $adminPeer = User::factory()->create(['status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $operator = User::factory()->create(['status' => 'active']);

        $admin->tenants()->attach($tenant->id, ['role' => 'tenant_admin', 'status' => 'active']);
        $adminPeer->tenants()->attach($tenant->id, ['role' => 'tenant_admin', 'status' => 'active']);
        $owner->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);
        $operator->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        $this->assertTrue(Gate::forUser($admin)->allows('manage_users'));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $owner));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $adminPeer));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $operator));
    }

    public function test_tenant_admin_can_create_but_owner_has_broader_edit(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Team T2',
            'slug' => 'teamt2',
            'status' => 'active',
        ]);
        $this->bindTenantPanel($tenant);

        $admin = User::factory()->create(['status' => 'active']);
        $owner = User::factory()->create(['status' => 'active']);
        $admin->tenants()->attach($tenant->id, ['role' => 'tenant_admin', 'status' => 'active']);
        $owner->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($owner)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $admin));
    }

    public function test_operator_with_manage_users_absent_cannot_create_even_if_hypothetical(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Team T3',
            'slug' => 'teamt3',
            'status' => 'active',
        ]);
        $this->bindTenantPanel($tenant);

        $operator = User::factory()->create(['status' => 'active']);
        $operator->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        $this->assertFalse(Gate::forUser($operator)->allows('manage_users'));
        $this->assertFalse(Gate::forUser($operator)->allows('create', User::class));
    }
}
