<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Platform;

use App\Filament\Platform\Resources\TenantResource\Pages\ListTenants;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Smoke: связь-сортировка колонки «Хранилище» ({@see TenantResource}) не должна падать запросом.
 */
final class TenantResourceStorageSortSmokeTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_sorting_by_storage_quota_used_bytes_does_not_error(): void
    {
        $this->createTenantWithActiveDomain('stq-sort-a');
        $this->createTenantWithActiveDomain('stq-sort-b');

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(ListTenants::class)
            ->assertSuccessful()
            ->call('sortTable', 'storageQuota.used_bytes', 'desc')
            ->assertSuccessful()
            ->call('sortTable', 'storageQuota.used_bytes', 'asc')
            ->assertSuccessful();
    }
}
