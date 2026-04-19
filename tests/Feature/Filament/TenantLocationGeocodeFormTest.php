<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Resources\TenantLocationResource\Pages\CreateTenantLocation;
use App\Filament\Tenant\Resources\TenantLocationResource\Pages\EditTenantLocation;
use App\Geocoding\GeocodePlacesService;
use App\Models\TenantLocation;
use App\Models\User;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class TenantLocationGeocodeFormTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        Config::set('services.nominatim.enabled', true);
        Config::set('services.nominatim.base_url', 'https://nominatim.openstreetmap.org');
        Config::set('services.nominatim.contact', 'test@example.com');
        Config::set('services.nominatim.timeout', 5);
        Config::set('services.nominatim.search_cache_ttl', 3600);
        Config::set('services.nominatim.pick_cache_ttl', 3600);
    }

    public function test_create_tenant_location_without_geocode_persists_manual_geo_fields(): void
    {
        Http::fake();

        $tenant = $this->createTenantWithActiveDomain('fil_loc_geo_manual');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(CreateTenantLocation::class)
            ->fillForm([
                'name' => 'Пункт выдачи Центр',
                'city' => 'Челябинск',
                'region' => 'Челябинская область',
                'country' => 'Россия',
            ])
            ->call('create')
            ->assertHasNoErrors();

        Http::assertNothingSent();

        $this->assertDatabaseHas('tenant_locations', [
            'tenant_id' => $tenant->id,
            'name' => 'Пункт выдачи Центр',
            'city' => 'Челябинск',
            'region' => 'Челябинская область',
            'country' => 'Россия',
        ]);
    }

    public function test_geocode_pick_prefills_city_region_country_on_create(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'place_id' => 900_001,
                    'class' => 'place',
                    'type' => 'city',
                    'display_name' => 'Chelyabinsk, Oblast, Russia',
                    'address' => [
                        'city' => 'Chelyabinsk',
                        'state' => 'Chelyabinsk Oblast',
                        'country' => 'Russia',
                    ],
                ],
            ], 200),
        ]);

        Cache::flush();

        $tenant = $this->createTenantWithActiveDomain('fil_loc_geo_pick');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        $geocoder = app(GeocodePlacesService::class);
        $options = $geocoder->searchOptions('Chelyabinsk');
        $this->assertNotEmpty($options);
        $pickId = (string) array_key_first($options);

        Livewire::test(CreateTenantLocation::class)
            ->fillForm([
                'name' => 'From geocode',
                '_geocode_pick' => $pickId,
            ])
            ->assertSet('data.city', 'Chelyabinsk')
            ->assertSet('data.region', 'Chelyabinsk Oblast')
            ->assertSet('data.country', 'Russia')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_locations', [
            'tenant_id' => $tenant->id,
            'name' => 'From geocode',
            'city' => 'Chelyabinsk',
            'region' => 'Chelyabinsk Oblast',
            'country' => 'Russia',
        ]);
    }

    public function test_edit_tenant_location_does_not_mutate_geo_on_mount(): void
    {
        Http::fake();

        $tenant = $this->createTenantWithActiveDomain('fil_loc_geo_edit');
        $loc = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Stored',
            'slug' => 'stored',
            'city' => 'X',
            'region' => 'Y',
            'country' => 'Z',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(EditTenantLocation::class, ['record' => $loc->getKey()])
            ->assertSuccessful()
            ->assertSet('data.city', 'X')
            ->assertSet('data.region', 'Y')
            ->assertSet('data.country', 'Z');

        Http::assertNothingSent();
    }
}
