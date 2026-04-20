<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\MotorcycleLocationMode;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Models\TenantLocation;
use App\Services\Catalog\TenantPublicCatalogLocationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class PublicBookingFleetPerUnitAndCatalogTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    private function postTenantJson(string $host, string $path, array $payload): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('POST', 'http://'.$host.$path, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_calculate_succeeds_for_per_unit_fleet_when_unit_linked_to_catalog_location(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('pub_pu_fleet');
        $host = $this->tenancyHostForSlug('pub_pu_fleet');

        $locA = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point A',
            'slug' => 'point-a',
            'is_active' => true,
        ]);
        TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point B',
            'slug' => 'point-b',
            'is_active' => true,
        ]);

        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'PerUnit Fleet',
            'slug' => 'perunit-fleet',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 3000,
            'uses_fleet_units' => true,
            'location_mode' => MotorcycleLocationMode::PerUnit,
        ]);

        $unit = RentalUnit::query()->create([
            'tenant_id' => $tenant->id,
            'motorcycle_id' => $m->id,
            'status' => 'active',
        ]);
        $unit->tenantLocations()->sync([$locA->id]);

        $start = now()->addDays(5)->format('Y-m-d');
        $end = now()->addDays(6)->format('Y-m-d');

        $response = $this->postTenantJson($host, '/booking/calculate?location='.$locA->slug, [
            'motorcycle_id' => $m->id,
            'rental_unit_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'addons' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('available', true);
        $response->assertJsonPath('rental_unit_id', $unit->id);
    }

    public function test_calculate_returns_422_for_per_unit_fleet_at_location_without_units(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('pub_pu_badloc');
        $host = $this->tenancyHostForSlug('pub_pu_badloc');

        $locA = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point A',
            'slug' => 'point-a',
            'is_active' => true,
        ]);
        $locB = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point B',
            'slug' => 'point-b',
            'is_active' => true,
        ]);

        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'PerUnit Fleet',
            'slug' => 'perunit-fleet-b',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 3000,
            'uses_fleet_units' => true,
            'location_mode' => MotorcycleLocationMode::PerUnit,
        ]);

        $unit = RentalUnit::query()->create([
            'tenant_id' => $tenant->id,
            'motorcycle_id' => $m->id,
            'status' => 'active',
        ]);
        $unit->tenantLocations()->sync([$locA->id]);

        $start = now()->addDays(5)->format('Y-m-d');
        $end = now()->addDays(6)->format('Y-m-d');

        $response = $this->postTenantJson($host, '/booking/calculate?location='.$locB->slug, [
            'motorcycle_id' => $m->id,
            'rental_unit_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'addons' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('available', false);
    }

    public function test_checkout_redirects_when_selected_location_motorcycle_not_visible_at_session_catalog(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pub_chk_loc');
        $host = $this->tenancyHostForSlug('pub_chk_loc');

        $locA = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point A',
            'slug' => 'point-a',
            'is_active' => true,
        ]);
        $locB = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point B',
            'slug' => 'point-b',
            'is_active' => true,
        ]);

        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loc Bike',
            'slug' => 'loc-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 3000,
            'uses_fleet_units' => false,
            'location_mode' => MotorcycleLocationMode::Selected,
        ]);
        $m->tenantLocations()->sync([$locA->id]);

        $start = now()->addDays(5)->format('Y-m-d');
        $end = now()->addDays(6)->format('Y-m-d');

        $response = $this->withSession([
            TenantPublicCatalogLocationService::SESSION_KEY => $locB->slug,
            'booking_draft' => [
                'motorcycle_id' => $m->id,
                'rental_unit_id' => null,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('GET', 'http://'.$host.'/checkout');

        $response->assertRedirect(route('booking.index'));
    }

    public function test_checkout_uses_draft_catalog_location_snapshot_over_remembered_session_location(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pub_chk_draft_loc');
        $host = $this->tenancyHostForSlug('pub_chk_draft_loc');

        $locA = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point A',
            'slug' => 'point-a',
            'is_active' => true,
        ]);
        $locB = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Point B',
            'slug' => 'point-b',
            'is_active' => true,
        ]);

        $m = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loc Bike Draft',
            'slug' => 'loc-bike-draft',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 3000,
            'uses_fleet_units' => false,
            'location_mode' => MotorcycleLocationMode::Selected,
        ]);
        $m->tenantLocations()->sync([$locA->id]);

        $start = now()->addDays(5)->format('Y-m-d');
        $end = now()->addDays(6)->format('Y-m-d');

        $response = $this->withSession([
            TenantPublicCatalogLocationService::SESSION_KEY => $locB->slug,
            'booking_draft' => [
                'motorcycle_id' => $m->id,
                'rental_unit_id' => null,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
                'public_catalog_location_id' => $locA->id,
            ],
        ])->call('GET', 'http://'.$host.'/checkout');

        $response->assertOk();
    }
}
