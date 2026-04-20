<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\ContactChannels\TenantContactChannelsStore;
use App\Models\Booking;
use App\Models\Motorcycle;
use App\Models\TenantLocation;
use App\Services\BookingService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class PublicBookingCheckoutConsentTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_store_checkout_requires_legal_consent_checkboxes(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_consent', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_consent');

        $start = now()->addDays(3)->format('Y-m-d');
        $end = now()->addDays(5)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consent Bike',
            'slug' => 'consent-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'phone',
        ]);

        $response->assertSessionHasErrors(['agree_to_terms', 'agree_to_privacy']);
    }

    public function test_store_checkout_succeeds_with_consents_and_stores_legal_snapshot(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_ok', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_ok');

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'OK Bike',
            'slug' => 'ok-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'phone',
        ]);

        $response->assertRedirect();
        $booking = Booking::query()->where('tenant_id', $tenant->id)->where('motorcycle_id', $bike->id)->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertIsArray($booking->legal_acceptances_json);
        $this->assertArrayHasKey('accepted_at', $booking->legal_acceptances_json);
        $this->assertArrayHasKey('terms_url', $booking->legal_acceptances_json);
        $this->assertArrayHasKey('privacy_url', $booking->legal_acceptances_json);
    }

    public function test_store_checkout_persists_public_catalog_location_id_on_booking(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_loc_id', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_loc_id');

        $loc = TenantLocation::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pickup Point',
            'slug' => 'pickup-point',
            'is_active' => true,
        ]);

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loc Bike',
            'slug' => 'loc-bike-id',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
                'public_catalog_location_id' => $loc->id,
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'phone',
        ]);

        $response->assertRedirect();
        $booking = Booking::query()->where('tenant_id', $tenant->id)->where('motorcycle_id', $bike->id)->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertSame((int) $loc->id, (int) $booking->public_catalog_location_id);
    }

    public function test_store_checkout_requires_preferred_contact_value_for_telegram(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_tg_req', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_tg_req');

        $state = app(TenantContactChannelsStore::class)->resolvedState($tenant->id);
        $map = [];
        foreach ($state as $key => $cfg) {
            $map[$key] = $cfg->toArray();
        }
        $map['telegram'] = [
            'uses_channel' => true,
            'public_visible' => true,
            'allowed_in_forms' => true,
            'business_value' => '@support',
            'sort_order' => 30,
        ];
        app(TenantContactChannelsStore::class)->persist($tenant->id, $map);

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'TG Bike',
            'slug' => 'tg-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'telegram',
            'preferred_contact_value' => '',
        ]);

        $response->assertSessionHasErrors('preferred_contact_value');
    }

    public function test_store_checkout_rejects_telegram_preferred_contact_value_with_invalid_at_signs(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_tg_at', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_tg_at');

        $state = app(TenantContactChannelsStore::class)->resolvedState($tenant->id);
        $map = [];
        foreach ($state as $key => $cfg) {
            $map[$key] = $cfg->toArray();
        }
        $map['telegram'] = [
            'uses_channel' => true,
            'public_visible' => true,
            'allowed_in_forms' => true,
            'business_value' => '@support',
            'sort_order' => 30,
        ];
        app(TenantContactChannelsStore::class)->persist($tenant->id, $map);

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'TG Bike At',
            'slug' => 'tg-bike-at',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'telegram',
            'preferred_contact_value' => 'невалидный @@',
        ]);

        $response->assertSessionHasErrors('preferred_contact_value');
    }

    public function test_store_checkout_rejects_invalid_telegram_preferred_contact_value(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_tg_bad', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_tg_bad');

        $state = app(TenantContactChannelsStore::class)->resolvedState($tenant->id);
        $map = [];
        foreach ($state as $key => $cfg) {
            $map[$key] = $cfg->toArray();
        }
        $map['telegram'] = [
            'uses_channel' => true,
            'public_visible' => true,
            'allowed_in_forms' => true,
            'business_value' => '@support',
            'sort_order' => 30,
        ];
        app(TenantContactChannelsStore::class)->persist($tenant->id, $map);

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'TG Bike Bad',
            'slug' => 'tg-bike-bad',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'telegram',
            'preferred_contact_value' => 'абв',
        ]);

        $response->assertSessionHasErrors('preferred_contact_value');
    }

    public function test_store_checkout_rejects_invalid_vk_preferred_contact_value(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('chk_vk_bad', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_vk_bad');

        $state = app(TenantContactChannelsStore::class)->resolvedState($tenant->id);
        $map = [];
        foreach ($state as $key => $cfg) {
            $map[$key] = $cfg->toArray();
        }
        $map['vk'] = [
            'uses_channel' => true,
            'public_visible' => true,
            'allowed_in_forms' => true,
            'business_value' => 'https://vk.com/id1',
            'sort_order' => 40,
        ];
        app(TenantContactChannelsStore::class)->persist($tenant->id, $map);

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'VK Bike Bad',
            'slug' => 'vk-bike-bad',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'vk',
            'preferred_contact_value' => 'vk',
        ]);

        $response->assertSessionHasErrors('preferred_contact_value');
    }

    public function test_store_checkout_survives_availability_race_between_precheck_and_create(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->partialMock(BookingService::class, function ($mock): void {
            $mock->shouldReceive('isAvailableForMotorcycle')->twice()->andReturn(true, false);
        });

        $tenant = $this->createTenantWithActiveDomain('chk_race_live', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_race_live');

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Race Live Bike',
            'slug' => 'race-live-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'phone',
        ]);

        $response->assertRedirect(route('booking.checkout'));
        $response->assertSessionHas(
            'error',
            'Выбранные даты больше недоступны. Выберите другие даты или оформите бронь заново.',
        );
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_store_checkout_invalid_argument_from_service_redirects_to_checkout_with_error(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(BookingService::class, function ($mock): void {
            $mock->shouldReceive('isAvailableForMotorcycle')->andReturn(true);
            $mock->shouldReceive('createPublicBooking')
                ->once()
                ->andThrow(new \InvalidArgumentException('Выбранные даты больше недоступны.'));
        });

        $tenant = $this->createTenantWithActiveDomain('chk_race', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('chk_race');

        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(12)->format('Y-m-d');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Race Bike',
            'slug' => 'race-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
            'uses_fleet_units' => false,
        ]);

        $response = $this->withSession([
            'booking_draft' => [
                'motorcycle_id' => $bike->id,
                'start_date' => $start,
                'end_date' => $end,
                'addons' => [],
            ],
        ])->call('POST', 'http://'.$host.'/checkout', [
            'agree_to_terms' => '1',
            'agree_to_privacy' => '1',
            'customer_name' => 'Иван Тестовый',
            'phone' => '+79991112233',
            'preferred_contact_channel' => 'phone',
        ]);

        $response->assertRedirect(route('booking.checkout'));
        $response->assertSessionHas('error', 'Выбранные даты больше недоступны.');
    }
}
