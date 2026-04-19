<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\MotorcycleLocationMode;
use App\Filament\Tenant\Resources\MotorcycleResource\Pages\EditMotorcycle;
use App\Filament\Tenant\Resources\TenantLocationResource;
use Filament\Forms\Components\CheckboxList;
use App\Livewire\Tenant\Motorcycles\MotorcycleMainInfoEditor;
use App\Livewire\Tenant\Motorcycles\MotorcycleRentalUnitsPanel;
use App\Models\Motorcycle;
use App\Models\User;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class MotorcycleBlockEditorsTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();
    }

    public function test_edit_motorcycle_shell_has_no_global_save_changes_action(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_block_shell');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Shell Save Test',
            'slug' => 'shell-save-test',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(EditMotorcycle::class, ['record' => $m->getKey()])
            ->assertSuccessful()
            ->assertDontSee('Сохранить изменения', false);
    }

    public function test_edit_motorcycle_shell_save_does_not_update_record(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_block_noop');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Noop Save Test',
            'slug' => 'noop-save-test',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        $before = $m->fresh()->updated_at;

        Livewire::test(EditMotorcycle::class, ['record' => $m->getKey()])
            ->call('save')
            ->assertSuccessful();

        $this->assertTrue($m->fresh()->updated_at->equalTo($before));
        $this->assertSame('Noop Save Test', $m->fresh()->name);
    }

    public function test_motorcycle_main_info_editor_saves_whitelisted_fields(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_main_block');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Block',
            'slug' => 'main-block',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
            'brand' => 'OldBrand',
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(MotorcycleMainInfoEditor::class, ['recordId' => (int) $m->getKey()])
            ->set('data.brand', 'NewBrand')
            ->call('save')
            ->assertSuccessful();

        $this->assertSame('NewBrand', $m->fresh()->brand);
        $this->assertSame('Main Block', $m->fresh()->name);
    }

    public function test_motorcycle_main_info_editor_validation_failure_preserves_local_state(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_main_val');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Valid Name',
            'slug' => 'valid-name',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(MotorcycleMainInfoEditor::class, ['recordId' => (int) $m->getKey()])
            ->set('data.name', '')
            ->call('save')
            ->assertHasErrors(['data.name']);

        $this->assertSame('Valid Name', $m->fresh()->name);
    }

    public function test_motorcycle_rental_units_panel_mounts_without_schema_error(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_units_panel');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Units Panel',
            'slug' => 'units-panel',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(MotorcycleRentalUnitsPanel::class, ['motorcycleId' => (int) $m->getKey()])
            ->assertSuccessful();
    }

    public function test_motorcycle_rental_units_panel_create_shows_helper_link_when_no_active_locations(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_units_no_locs');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Units No Locs',
            'slug' => 'units-no-locs',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
            'uses_fleet_units' => true,
            'location_mode' => MotorcycleLocationMode::PerUnit,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        $createLocationsUrl = TenantLocationResource::getUrl('create');

        $test = Livewire::test(MotorcycleRentalUnitsPanel::class, ['motorcycleId' => (int) $m->getKey()])
            ->mountTableAction('create')
            ->assertTableActionMounted('create')
            ->call('forceRender');

        $instance = $test->instance();
        $this->assertInstanceOf(MotorcycleRentalUnitsPanel::class, $instance);
        $schemaName = $instance->getMountedActionSchemaName();
        $this->assertNotNull($schemaName);
        $schema = $instance->getSchema($schemaName);
        $this->assertNotNull($schema);
        $locationsField = $schema->getComponent(
            fn ($component): bool => $component instanceof CheckboxList
                && $component->getName() === 'tenant_location_ids',
        );
        $this->assertInstanceOf(CheckboxList::class, $locationsField);
        $this->assertSame([], $locationsField->getOptions());

        $test
            ->assertSee('В справочнике нет активных локаций', false, false)
            ->assertSee('Добавить локацию', false, false)
            ->assertSee($createLocationsUrl, false, false);
    }

    public function test_motorcycle_rental_units_panel_create_table_action_submits_schema(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_units_create_schema');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Units Create Schema',
            'slug' => 'units-create-schema',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(MotorcycleRentalUnitsPanel::class, ['motorcycleId' => (int) $m->getKey()])
            ->callTableAction('create', data: [
                'unit_label' => 'Smoke unit',
                'status' => 'active',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_units', [
            'motorcycle_id' => $m->id,
            'tenant_id' => $tenant->id,
            'unit_label' => 'Smoke unit',
            'status' => 'active',
        ]);
    }

    public function test_motorcycle_rental_units_panel_import_csv_action_mounts_with_table_context(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_moto_units_import_schema');
        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Units Import Schema',
            'slug' => 'units-import-schema',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(MotorcycleRentalUnitsPanel::class, ['motorcycleId' => (int) $m->getKey()])
            ->mountTableAction('importCsv')
            ->assertTableActionMounted('importCsv');
    }
}
