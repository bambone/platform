<?php

namespace Tests\Unit\Product\CRM\BookingWorkspace;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Motorcycle;
use App\Product\CRM\BookingWorkspace\BookingWorkspaceAvailabilityState;
use App\Product\CRM\BookingWorkspace\CrmRequestBookingWorkspaceAssembler;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class CrmRequestBookingWorkspaceAssemblerTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_platform_scoped_crm_returns_no_data_without_timeline(): void
    {
        $crm = $this->makeCrmRequest(null, ['request_type' => 'platform_contact']);

        $dto = app(CrmRequestBookingWorkspaceAssembler::class)->assemble($crm);

        $this->assertFalse($dto->hasBookingContext);
        $this->assertSame(BookingWorkspaceAvailabilityState::NoData, $dto->availabilityState);
        $this->assertFalse($dto->showTimelinePanel);
        $this->assertFalse($dto->showInsightsPanel);
    }

    public function test_available_when_no_overlapping_booking_without_rental_units(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bwassem');
        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Assembler Bike',
            'slug' => 'assembler-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 3000,
        ]);
        $crm = $this->makeCrmRequest($tenant->id);
        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'crm_request_id' => $crm->id,
            'name' => 'Lead',
            'phone' => '+79991112200',
            'status' => 'new',
            'motorcycle_id' => $bike->id,
            'rental_date_from' => '2026-09-10',
            'rental_date_to' => '2026-09-14',
        ]);

        $dto = app(CrmRequestBookingWorkspaceAssembler::class)->assemble(
            $crm->fresh(['leads', 'tenant'])
        );

        $this->assertSame('lead', $dto->source);
        $this->assertTrue($dto->showTimelinePanel);
        $this->assertSame(BookingWorkspaceAvailabilityState::Available, $dto->availabilityState);
        $this->assertSame(0, $dto->conflictsCount);
    }

    public function test_conflict_when_occupying_booking_overlaps_requested_range(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bwovlp');
        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Overlap Bike',
            'slug' => 'overlap-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 4000,
        ]);
        $crm = $this->makeCrmRequest($tenant->id);
        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'crm_request_id' => $crm->id,
            'name' => 'Lead',
            'phone' => '+79991112201',
            'status' => 'new',
            'motorcycle_id' => $bike->id,
            'rental_date_from' => '2026-10-05',
            'rental_date_to' => '2026-10-08',
        ]);

        Booking::query()->create([
            'tenant_id' => $tenant->id,
            'bike_id' => null,
            'motorcycle_id' => $bike->id,
            'rental_unit_id' => null,
            'start_date' => '2026-10-06',
            'end_date' => '2026-10-07',
            'start_at' => '2026-10-06 00:00:00',
            'end_at' => '2026-10-07 23:59:59',
            'status' => BookingStatus::CONFIRMED,
            'price_per_day_snapshot' => 4000,
            'total_price' => 8000,
            'customer_name' => 'Other',
            'phone' => '+79991112299',
            'phone_normalized' => PhoneNormalizer::normalize('+79991112299'),
            'source' => 'test',
        ]);

        $dto = app(CrmRequestBookingWorkspaceAssembler::class)->assemble(
            $crm->fresh(['leads', 'tenant'])
        );

        $this->assertSame(BookingWorkspaceAvailabilityState::Conflict, $dto->availabilityState);
        $this->assertGreaterThan(0, $dto->conflictsCount);
        $this->assertNotEmpty($dto->conflictingBookingsCompact);
    }

    public function test_multiple_leads_warns_and_uses_highest_id(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bwmult');
        $bikeA = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bike A',
            'slug' => 'bike-a',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1000,
        ]);
        $bikeB = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bike B',
            'slug' => 'bike-b',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 2000,
        ]);
        $crm = $this->makeCrmRequest($tenant->id);
        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'crm_request_id' => $crm->id,
            'name' => 'First',
            'phone' => '+79991112202',
            'status' => 'new',
            'motorcycle_id' => $bikeA->id,
            'rental_date_from' => '2026-11-01',
            'rental_date_to' => '2026-11-03',
        ]);
        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'crm_request_id' => $crm->id,
            'name' => 'Second',
            'phone' => '+79991112203',
            'status' => 'new',
            'motorcycle_id' => $bikeB->id,
            'rental_date_from' => '2026-11-10',
            'rental_date_to' => '2026-11-12',
        ]);

        $dto = app(CrmRequestBookingWorkspaceAssembler::class)->assemble(
            $crm->fresh(['leads', 'tenant'])
        );

        $this->assertStringContainsString('несколько', implode(' ', $dto->warnings));
        $this->assertSame($bikeB->id, $dto->motorcycleId);
        $this->assertSame('Bike B', $dto->motorcycleTitle);
    }

    public function test_payload_divergence_warning_when_lead_is_canonical(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bwpayl');
        $bike = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Canon Bike',
            'slug' => 'canon-bike',
            'status' => 'available',
            'show_in_catalog' => true,
            'price_per_day' => 1500,
        ]);
        $crm = $this->makeCrmRequest($tenant->id, [
            'payload_json' => [
                'motorcycle_id' => 99999,
                'rental_date_from' => '2025-01-01',
                'rental_date_to' => '2025-01-05',
            ],
        ]);
        Lead::query()->create([
            'tenant_id' => $tenant->id,
            'crm_request_id' => $crm->id,
            'name' => 'Lead',
            'phone' => '+79991112204',
            'status' => 'new',
            'motorcycle_id' => $bike->id,
            'rental_date_from' => '2026-12-01',
            'rental_date_to' => '2026-12-04',
        ]);

        $dto = app(CrmRequestBookingWorkspaceAssembler::class)->assemble(
            $crm->fresh(['leads', 'tenant'])
        );

        $this->assertSame($bike->id, $dto->motorcycleId);
        $this->assertStringContainsString('payload', implode(' ', $dto->warnings));
        $this->assertStringContainsString('Lead', implode(' ', $dto->warnings));
    }
}
