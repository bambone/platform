<?php

namespace Tests\Feature\Tenant;

use App\Models\Lead;
use App\Models\Motorcycle;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Регрессии публичного сценария: период аренды в шапке → каталог/карточка → модалка → POST /api/leads.
 * JS не гоняем в CI; проверяем разметку/скрипты и серверный приём заявки.
 */
class TenantPublicBookingFlowTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function getWithHost(string $host, string $path = '/'): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('GET', 'http://'.$host.$path);
    }

    protected function postWithHost(string $host, string $path, array $data): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('POST', 'http://'.$host.$path, $data);
    }

    public function test_tenant_layout_exposes_alpine_root_and_rental_period_store(): void
    {
        $this->createTenantWithActiveDomain('flowroot');

        $html = $this->getWithHost($this->tenancyHostForSlug('flowroot'), '/')->assertOk()->getContent();

        $this->assertStringContainsString('x-data="{}"', $html);
        $this->assertStringContainsString('/api/tenant/booking/catalog-availability', $html);
        $this->assertStringContainsString("Alpine.store('tenantBooking'", $html);
        $this->assertStringContainsString('tenant_rental_period_v1', $html);
    }

    public function test_home_booking_bar_submits_through_tenant_booking_store(): void
    {
        $this->createTenantWithActiveDomain('flowhome');

        $html = $this->getWithHost($this->tenancyHostForSlug('flowhome'), '/')->assertOk()->getContent();

        $this->assertStringContainsString('$store.tenantBooking.applyCatalogSearch()', $html);
        $this->assertStringContainsString('applyCatalogSearch({ scrollToCatalog: false })', $html);
        $this->assertStringContainsString('TenantDatePickers?.initBar', $html);
        $this->assertStringContainsString('flatpickr@4.6.13', $html);
        $this->assertStringContainsString('id="start_date"', $html);
        $this->assertStringContainsString('id="end_date"', $html);
    }

    public function test_motorcycle_show_includes_booking_modal_dispatch_with_store_dates(): void
    {
        $tenant = $this->createTenantWithActiveDomain('flowmoto');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Flow Test Bike',
            'slug' => 'flow-test-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4500,
        ]);

        $html = $this->getWithHost($this->tenancyHostForSlug('flowmoto'), '/moto/'.$bike->slug)
            ->assertOk()
            ->assertSee('Flow Test Bike', false)
            ->getContent();

        $this->assertStringContainsString('$store.tenantBooking.filters.start_date', $html);
        $this->assertStringContainsString('open-booking-modal', $html);
        $this->assertStringContainsString('TenantIntlPhone', $html);
        $this->assertStringContainsString('+7 (999) 123-45-67', $html);
        $this->assertStringContainsString('Перед отправкой заявки', $html);
    }

    public function test_tenant_post_json_booking_lead_with_motorcycle_and_dates_creates_lead(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('flowlead');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'API Bike',
            'slug' => 'api-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 5000,
        ]);

        $host = $this->tenancyHostForSlug('flowlead');

        $response = $this->call('POST', 'http://'.$host.'/api/leads', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Иван Тестовый',
            'phone' => '+79997776655',
            'email' => null,
            'comment' => 'Нужен байк на выходные',
            'motorcycle_id' => $bike->id,
            'rental_date_from' => '2026-04-10',
            'rental_date_to' => '2026-04-12',
            'source' => 'booking_form',
            'agree_to_terms' => true,
            'agree_to_privacy' => true,
        ], JSON_THROW_ON_ERROR));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $leadId = (int) $response->json('lead_id');
        $this->assertGreaterThan(0, $leadId);

        $lead = Lead::query()->findOrFail($leadId);
        $this->assertSame($tenant->id, $lead->tenant_id);
        $this->assertSame($bike->id, $lead->motorcycle_id);
        $this->assertSame('2026-04-10', $lead->rental_date_from->format('Y-m-d'));
        $this->assertSame('2026-04-12', $lead->rental_date_to->format('Y-m-d'));
        $this->assertSame('phone', $lead->preferred_contact_channel);
        $this->assertIsArray($lead->visitor_contact_channels_json);
        $this->assertSame('phone', $lead->visitor_contact_channels_json[0]['type'] ?? null);
        $this->assertSame('+79997776655', $lead->visitor_contact_channels_json[0]['value'] ?? null);
        $this->assertIsArray($lead->legal_acceptances_json);
        $this->assertArrayHasKey('accepted_at', $lead->legal_acceptances_json);
    }

    public function test_tenant_post_json_booking_lead_accepts_masked_russian_phone(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('flowmask');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mask Bike',
            'slug' => 'mask-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 5000,
        ]);

        $host = $this->tenancyHostForSlug('flowmask');

        $response = $this->call('POST', 'http://'.$host.'/api/leads', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Иван Тестовый',
            'phone' => '+7 (951) 784-58-89',
            'email' => null,
            'comment' => null,
            'motorcycle_id' => $bike->id,
            'rental_date_from' => '2026-04-10',
            'rental_date_to' => '2026-04-12',
            'source' => 'booking_form',
            'agree_to_terms' => true,
            'agree_to_privacy' => true,
        ], JSON_THROW_ON_ERROR));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $lead = Lead::query()->findOrFail((int) $response->json('lead_id'));
        $this->assertSame('+79517845889', $lead->phone);
        $this->assertSame('phone', $lead->preferred_contact_channel);
    }

    public function test_tenant_post_json_booking_lead_with_motorcycle_requires_legal_consents(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $tenant = $this->createTenantWithActiveDomain('flowconsent');

        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consent Bike',
            'slug' => 'consent-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 5000,
        ]);

        $host = $this->tenancyHostForSlug('flowconsent');

        $response = $this->call('POST', 'http://'.$host.'/api/leads', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Иван Тестовый',
            'phone' => '+79997776655',
            'email' => null,
            'comment' => null,
            'motorcycle_id' => $bike->id,
            'rental_date_from' => '2026-04-10',
            'rental_date_to' => '2026-04-12',
            'source' => 'booking_form',
        ], JSON_THROW_ON_ERROR));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['agree_to_terms', 'agree_to_privacy']);
    }
}
