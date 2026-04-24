<?php

declare(strict_types=1);

namespace Tests\Feature\TenantFiles;

use App\Filament\Tenant\Pages\TenantFilesPage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class TenantFilesPageGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_manager_cannot_access_tenant_files_page(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 'tf-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'content_manager', 'status' => 'active']);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        $this->assertFalse(Gate::allows('manage_tenant_files'));
        $this->assertFalse(TenantFilesPage::canAccess());
    }

    public function test_storage_owner_gets_manage_tenant_files(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'O',
            'slug' => 'tf2-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(CurrentTenantManager::class)->setTenant($tenant);

        $this->assertTrue(Gate::allows('manage_tenant_files'));
        $this->assertTrue(TenantFilesPage::canAccess());
    }
}
