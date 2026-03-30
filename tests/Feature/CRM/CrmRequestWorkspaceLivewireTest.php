<?php

namespace Tests\Feature\CRM;

use App\Livewire\Crm\CrmRequestWorkspace;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class CrmRequestWorkspaceLivewireTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_platform_user_mounts_workspace_for_platform_scoped_crm_without_guard_authorize_error(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');
        $crm = $this->makeCrmRequest(null, ['request_type' => 'platform_contact']);

        $html = Livewire::actingAs($user)
            ->test(CrmRequestWorkspace::class, ['crmRequestId' => $crm->id])
            ->assertOk()
            ->html();

        $this->assertStringContainsString('width="14"', $html, 'CRM workspace SVGs must set explicit width (Tailwind alone is unreliable in Filament modals).');
        $this->assertStringContainsString('height="14"', $html);
        $this->assertStringContainsString('crm-svg-icon-host', $html);

        Filament::setCurrentPanel(null);
    }

    public function test_platform_user_cannot_mount_workspace_for_tenant_scoped_crm(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $tenant = $this->createTenantWithActiveDomain('tw');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');
        $tenantCrm = $this->makeCrmRequest($tenant->id, ['request_type' => 'tenant_booking']);

        Livewire::actingAs($user)
            ->test(CrmRequestWorkspace::class, ['crmRequestId' => $tenantCrm->id])
            ->assertForbidden();

        Filament::setCurrentPanel(null);
    }
}
