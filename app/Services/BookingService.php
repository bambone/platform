<?php

namespace App\Services;

use App\DTO\BookingData;
use App\Enums\BookingStatus;
use App\Jobs\SendBookingTelegramNotification;
use App\Models\Addon;
use App\Models\Bike;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\RentalUnit;
use Carbon\Carbon;
use Exception;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * @throws Exception
     */
    public function createBooking(BookingData $data): Booking
    {
        $bike = Bike::findOrFail($data->bike_id);

        if (! $this->isAvailable($bike->id, $data->start_date, $data->end_date)) {
            throw new Exception('The selected bike is not available for these dates.');
        }

        $startDate = Carbon::parse($data->start_date);
        $endDate = Carbon::parse($data->end_date);
        $days = $startDate->diffInDays($endDate) + 1;

        $totalPrice = $days * $bike->price_per_day;

        /** @var Booking $booking */
        $booking = Booking::create([
            'tenant_id' => $bike->tenant_id,
            'bike_id' => $bike->id,
            'start_date' => $data->start_date,
            'end_date' => $data->end_date,
            'start_at' => $startDate->startOfDay(),
            'end_at' => $endDate->endOfDay(),
            'status' => BookingStatus::PENDING,
            'price_per_day_snapshot' => $bike->price_per_day,
            'total_price' => $totalPrice,
            'customer_name' => $data->customer_name,
            'phone' => $data->phone,
            'phone_normalized' => $this->normalizePhone($data->phone),
            'source' => $data->source,
            'customer_comment' => $data->customer_comment,
        ]);

        SendBookingTelegramNotification::dispatch($booking);

        return $booking;
    }

    /**
     * Create booking from public checkout flow.
     */
    public function createPublicBooking(array $data): Booking
    {
        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
        $basePrice = $data['pricing_snapshot']['base_price'] ?? 0;

        $booking = Booking::create([
            'tenant_id' => $data['tenant_id'],
            'motorcycle_id' => $data['motorcycle_id'],
            'rental_unit_id' => $data['rental_unit_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'status' => BookingStatus::PENDING,
            'price_per_day_snapshot' => $days > 0 ? (int) round($basePrice / $days) : 0,
            'total_price' => $data['total_price'],
            'pricing_snapshot_json' => $data['pricing_snapshot'] ?? null,
            'deposit_amount' => $data['deposit_amount'] ?? 0,
            'payment_status' => 'pending',
            'customer_name' => $data['customer_name'],
            'phone' => $data['phone'],
            'phone_normalized' => $this->normalizePhone($data['phone']),
            'source' => $data['source'] ?? 'public_booking',
            'customer_comment' => $data['customer_comment'] ?? null,
        ]);

        foreach ($data['addons'] ?? [] as $addonId => $qty) {
            $addon = Addon::find($addonId);
            if ($addon && $qty > 0) {
                BookingAddon::create([
                    'booking_id' => $booking->id,
                    'addon_id' => $addon->id,
                    'quantity' => $qty,
                    'price_snapshot' => $addon->price,
                ]);
            }
        }

        if ($booking->rental_unit_id) {
            $this->availabilityService->blockForBooking($booking);
        }

        SendBookingTelegramNotification::dispatch($booking);

        return $booking;
    }

    public function isAvailable(int $bikeId, string $startDate, string $endDate): bool
    {
        return ! Booking::where('bike_id', $bikeId)
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->exists();
    }

    public function isAvailableForMotorcycle(int $motorcycleId, string $startDate, string $endDate): bool
    {
        $rentalUnits = RentalUnit::where('motorcycle_id', $motorcycleId)->where('status', 'active')->pluck('id');

        if ($rentalUnits->isEmpty()) {
            return ! Booking::where('motorcycle_id', $motorcycleId)
                ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        foreach ($rentalUnits as $unitId) {
            $unit = RentalUnit::find($unitId);
            if ($unit && $this->availabilityService->isAvailable($unit, $start, $end)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}
