<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Resources\SchedulingResourceResource\Pages\ListSchedulingResources;
use App\Models\SchedulingResource;
use App\Models\SchedulingResourceTypeLabel;
use App\Models\User;
use App\Scheduling\Enums\SchedulingResourceType;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\Enums\TentativeEventsPolicy;
use App\Scheduling\Enums\UnconfirmedRequestsPolicy;
use App\Scheduling\SchedulingTimezoneOptions;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Дымовая проверка списка «Ресурсы расписания» после join к scheduling_resource_type_labels (таблица Filament).
 */
final class SchedulingResourceTableFilamentTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();
    }

    public function test_list_scheduling_resources_table_renders_and_shows_record(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_sched_res_tbl');

        SchedulingResource::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenant->id,
            'resource_type' => SchedulingResourceType::Person->value,
            'user_id' => null,
            'label' => 'Smoke resource',
            'timezone' => SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
            'tentative_events_policy' => TentativeEventsPolicy::ProviderDefault,
            'unconfirmed_requests_policy' => UnconfirmedRequestsPolicy::Ignore,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(ListSchedulingResources::class)
            ->assertSuccessful()
            ->assertSee('Smoke resource', false);
    }

    public function test_table_global_search_by_resource_label_narrows_rows(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_sched_search_label');

        SchedulingResource::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenant->id,
            'resource_type' => SchedulingResourceType::Person->value,
            'user_id' => null,
            'label' => 'AlphaApple Resource',
            'timezone' => SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
            'tentative_events_policy' => TentativeEventsPolicy::ProviderDefault,
            'unconfirmed_requests_policy' => UnconfirmedRequestsPolicy::Ignore,
            'is_active' => true,
        ]);
        SchedulingResource::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenant->id,
            'resource_type' => SchedulingResourceType::Team->value,
            'user_id' => null,
            'label' => 'BetaBerry Resource',
            'timezone' => SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
            'tentative_events_policy' => TentativeEventsPolicy::ProviderDefault,
            'unconfirmed_requests_policy' => UnconfirmedRequestsPolicy::Ignore,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(ListSchedulingResources::class)
            ->assertSuccessful()
            ->assertCountTableRecords(2)
            ->searchTable('AlphaApple')
            ->assertCountTableRecords(1)
            ->assertSee('AlphaApple Resource', false);
    }

    public function test_table_global_search_matches_custom_resource_type_label(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_sched_search_type');

        SchedulingResourceTypeLabel::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'custom_yacht',
            'label' => 'FindMeCustomYachtType',
        ]);

        $withCustomType = SchedulingResource::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenant->id,
            'resource_type' => 'custom_yacht',
            'user_id' => null,
            'label' => 'Plain row label',
            'timezone' => SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
            'tentative_events_policy' => TentativeEventsPolicy::ProviderDefault,
            'unconfirmed_requests_policy' => UnconfirmedRequestsPolicy::Ignore,
            'is_active' => true,
        ]);

        SchedulingResource::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenant->id,
            'resource_type' => SchedulingResourceType::Person->value,
            'user_id' => null,
            'label' => 'Other unrelated',
            'timezone' => SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
            'tentative_events_policy' => TentativeEventsPolicy::ProviderDefault,
            'unconfirmed_requests_policy' => UnconfirmedRequestsPolicy::Ignore,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(ListSchedulingResources::class)
            ->assertSuccessful()
            ->assertCountTableRecords(2)
            ->searchTable('FindMeCustomYacht')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$withCustomType]);
    }
}
