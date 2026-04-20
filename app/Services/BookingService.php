<?php

namespace App\Services;

use App\Booking\PublicBookingCheckoutException;
use App\Booking\PublicBookingMotorcyclePolicy;
use App\DTO\BookingData;
use App\Enums\BookingStatus;
use App\Jobs\SendBookingTelegramNotification;
use App\Models\Bike;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\TenantLocation;
use App\MotorcyclePricing\BookingPricingHydrator;
use App\MotorcyclePricing\MotorcycleBookingPricingPolicy;
use App\MotorcyclePricing\RentalPricingDuration;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\Presenters\BookingNotificationPresenter;
use App\Services\Catalog\MotorcycleLocationCatalogService;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected NotificationEventRecorder $notificationRecorder,
        protected BookingNotificationPresenter $bookingNotifications,
        protected BookingPricingHydrator $bookingPricingHydrator,
        protected PricingService $pricingService,
        protected MotorcycleBookingPricingPolicy $motorcycleBookingPricingPolicy,
        protected MotorcycleLocationCatalogService $motorcycleLocationCatalog,
    ) {}

    /**
     * Tenant-scoped bike booking (JSON API on tenant host). Requires {@see currentTenant()}.
     *
     * @throws Exception
     * @throws \RuntimeException when tenant context is missing
     */
    public function createBooking(BookingData $data): Booking
    {
        $booking = DB::transaction(function () use ($data): Booking {
            $tenant = currentTenant();
            if ($tenant === null) {
                throw new \RuntimeException('Bike booking requires an active tenant context.');
            }

            $bike = Bike::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($data->bike_id)
                ->firstOrFail();

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
                'phone_normalized' => PhoneNormalizer::normalize($data->phone),
                'source' => $data->source,
                'customer_comment' => $data->customer_comment,
            ]);

            $this->dispatchBookingCreatedNotification($booking);

            return $booking;
        });

        if (config('notification_center.legacy_telegram_parallel')) {
            SendBookingTelegramNotification::dispatch($booking);
        }

        return $booking;
    }

    /**
     * Create booking from public checkout flow.
     *
     * Re-checks availability before insert (in addition to controller validation).
     * {@see BookingAddon} rows are created only from {@see PricingService::calculatePrice} snapshot lines
     * (active add-ons only), never from raw request maps alone — keeps totals / snapshot / lines consistent.
     */
    public function createPublicBooking(array $data): Booking
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            throw new PublicBookingCheckoutException(
                'Не удалось оформить бронирование. Обновите страницу и попробуйте снова.',
                forgetDraft: true,
                redirectToCatalog: true,
            );
        }

        $booking = DB::transaction(function () use ($data, $tenantId): Booking {
            $motorcycle = Motorcycle::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $data['motorcycle_id'])
                ->first();
            if ($motorcycle === null) {
                throw new PublicBookingCheckoutException(
                    'Модель не найдена или больше недоступна для бронирования. Выберите технику в каталоге заново.',
                    forgetDraft: true,
                    redirectToCatalog: true,
                );
            }

            if (! PublicBookingMotorcyclePolicy::isAllowedForPublicBooking($motorcycle)) {
                throw new PublicBookingCheckoutException(
                    'Эта модель недоступна для онлайн-бронирования.',
                    forgetDraft: true,
                    redirectToCatalog: true,
                );
            }

            [$rangeStart, $rangeEnd] = $this->publicBookingRangeBounds($data);

            $catalogLocation = null;
            $catalogLocationId = (int) ($data['public_catalog_location_id'] ?? 0);
            if ($catalogLocationId > 0) {
                $catalogLocation = TenantLocation::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($catalogLocationId)
                    ->first();
                if ($catalogLocation === null) {
                    throw new PublicBookingCheckoutException(
                        'Указана недопустимая точка каталога для бронирования.',
                        forgetDraft: true,
                        redirectToCatalog: true,
                    );
                }
            }

            $unit = null;
            if ($motorcycle->uses_fleet_units) {
                if (empty($data['rental_unit_id']) || (int) $data['rental_unit_id'] < 1) {
                    throw new PublicBookingCheckoutException(
                        'Для этой модели необходимо выбрать единицу парка.',
                        forgetDraft: true,
                        redirectToCatalog: true,
                    );
                }
                $unit = RentalUnit::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey((int) $data['rental_unit_id'])
                    ->first();
                if ($unit === null) {
                    throw new PublicBookingCheckoutException(
                        'Единица парка не найдена. Выберите даты на странице модели заново.',
                        forgetDraft: true,
                        redirectToCatalog: true,
                    );
                }
                if ((int) $unit->motorcycle_id !== (int) $data['motorcycle_id']) {
                    throw new PublicBookingCheckoutException(
                        'Единица парка не соответствует выбранной модели.',
                        forgetDraft: true,
                        redirectToCatalog: true,
                    );
                }
                if (! $this->motorcycleLocationCatalog->rentalUnitIsEligibleForPublic($motorcycle, $unit, $catalogLocation)) {
                    throw new PublicBookingCheckoutException(
                        'Единица парка недоступна для онлайн-бронирования.',
                        forgetDraft: true,
                        redirectToCatalog: true,
                    );
                }
                if (! $this->availabilityService->isAvailable($unit, $rangeStart, $rangeEnd)) {
                    throw new PublicBookingCheckoutException(
                        'Выбранные даты для этой техники больше недоступны. Выберите другие даты или оформите бронь заново.',
                    );
                }
            } elseif (! $this->isAvailableForMotorcycle((int) $data['motorcycle_id'], (string) $data['start_date'], (string) $data['end_date'])) {
                throw new PublicBookingCheckoutException(
                    'Выбранные даты больше недоступны. Выберите другие даты или оформите бронь заново.',
                );
            }

            $startDay = $rangeStart->copy()->startOfDay();
            $endDay = $rangeEnd->copy()->startOfDay();
            $days = RentalPricingDuration::inclusiveCalendarDays($startDay, $endDay);

            try {
                $this->motorcycleBookingPricingPolicy->assertPublicCheckoutPricingResolvable($motorcycle, $days);
            } catch (\InvalidArgumentException $e) {
                if ($e instanceof PublicBookingCheckoutException) {
                    throw $e;
                }
                throw new PublicBookingCheckoutException(
                    $e->getMessage(),
                    forgetDraft: true,
                    redirectToCatalog: true,
                    previous: $e,
                );
            }

            $addonIds = [];
            foreach ($data['addons'] ?? [] as $addonId => $qty) {
                $qty = is_numeric($qty) ? (int) $qty : 0;
                if ($qty > 0) {
                    $addonIds[(int) $addonId] = $qty;
                }
            }

            $target = $unit ?? $motorcycle;
            $pricing = $this->pricingService->calculatePrice($target, $startDay, $endDay, 'daily', $addonIds);
            $addonLines = is_array($pricing['addons'] ?? null) ? $pricing['addons'] : [];
            $basePrice = (int) ($pricing['base_price'] ?? 0);

            $v2 = $this->bookingPricingHydrator->bookingPricingAttributes($motorcycle, $days, $pricing, $addonLines);

            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'motorcycle_id' => $data['motorcycle_id'],
                'rental_unit_id' => $unit?->id,
                'public_catalog_location_id' => $catalogLocation?->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'status' => BookingStatus::PENDING,
                'price_per_day_snapshot' => $days > 0 ? (int) round($basePrice / max(1, $days)) : 0,
                'total_price' => (int) ($pricing['total'] ?? 0),
                'pricing_snapshot_json' => $v2['pricing_snapshot_json'],
                'pricing_snapshot_schema_version' => $v2['pricing_snapshot_schema_version'],
                'currency' => $v2['currency'],
                'rental_total_minor' => $v2['rental_total_minor'],
                'deposit_amount_minor' => $v2['deposit_amount_minor'],
                'payable_now_minor' => $v2['payable_now_minor'],
                'selected_tariff_id' => $v2['selected_tariff_id'],
                'selected_tariff_kind' => $v2['selected_tariff_kind'],
                'deposit_amount' => (int) ($pricing['deposit'] ?? 0),
                'payment_status' => 'pending',
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'preferred_contact_channel' => $data['preferred_contact_channel'] ?? null,
                'preferred_contact_value' => $data['preferred_contact_value'] ?? null,
                'visitor_contact_channels_json' => $data['visitor_contact_channels_json'] ?? null,
                'legal_acceptances_json' => $data['legal_acceptances_json'] ?? null,
                'phone_normalized' => PhoneNormalizer::normalize($data['phone']),
                'source' => $data['source'] ?? 'public_booking',
                'customer_comment' => $data['customer_comment'] ?? null,
            ]);

            foreach ($addonLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $addonId = (int) ($line['addon_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                $unitPrice = (int) ($line['price'] ?? 0);
                if ($addonId < 1 || $qty < 1) {
                    continue;
                }
                BookingAddon::create([
                    'booking_id' => $booking->id,
                    'addon_id' => $addonId,
                    'quantity' => $qty,
                    'price_snapshot' => $unitPrice,
                ]);
            }

            if ($booking->rental_unit_id) {
                $this->availabilityService->blockForBooking($booking);
            }

            $this->dispatchBookingCreatedNotification($booking);

            return $booking;
        });

        if (config('notification_center.legacy_telegram_parallel')) {
            SendBookingTelegramNotification::dispatch($booking);
        }

        return $booking;
    }

    private function dispatchBookingCreatedNotification(Booking $booking): void
    {
        $bookingId = (int) $booking->id;
        $tenantId = (int) $booking->tenant_id;
        DB::afterCommit(function () use ($bookingId, $tenantId): void {
            $fresh = Booking::query()->find($bookingId);
            $tenant = Tenant::query()->find($tenantId);
            if ($fresh === null || $tenant === null) {
                return;
            }

            $payload = $this->bookingNotifications->payloadForCreated($tenant, $fresh);
            $this->notificationRecorder->record(
                $tenantId,
                'booking.created',
                class_basename(Booking::class),
                $bookingId,
                $payload,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private function publicBookingRangeBounds(array $data): array
    {
        $startRaw = $data['start_at'] ?? null;
        $endRaw = $data['end_at'] ?? null;

        if ($startRaw instanceof Carbon) {
            $start = $startRaw->copy()->startOfDay();
        } elseif ($startRaw instanceof \DateTimeInterface) {
            $start = Carbon::instance($startRaw)->startOfDay();
        } else {
            $start = Carbon::parse((string) $data['start_date'])->startOfDay();
        }

        if ($endRaw instanceof Carbon) {
            $end = $endRaw->copy()->endOfDay();
        } elseif ($endRaw instanceof \DateTimeInterface) {
            $end = Carbon::instance($endRaw)->endOfDay();
        } else {
            $end = Carbon::parse((string) $data['end_date'])->endOfDay();
        }

        return [$start, $end];
    }

    public function isAvailable(int $bikeId, string $startDate, string $endDate): bool
    {
        return ! Booking::where('bike_id', $bikeId)
            ->whereIn('status', Booking::occupyingStatusValues())
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->exists();
    }

    public function isAvailableForMotorcycle(int $motorcycleId, string $startDate, string $endDate): bool
    {
        $rentalUnits = RentalUnit::query()
            ->where('motorcycle_id', $motorcycleId)
            ->where('status', 'active')
            ->get();

        if ($rentalUnits->isEmpty()) {
            return ! Booking::where('motorcycle_id', $motorcycleId)
                ->whereIn('status', Booking::occupyingStatusValues())
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        foreach ($rentalUnits as $unit) {
            if ($this->availabilityService->isAvailable($unit, $start, $end)) {
                return true;
            }
        }

        return false;
    }
}
