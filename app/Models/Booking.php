<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToTenant;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\Presenters\BookingNotificationPresenter;
use App\Services\AvailabilityService;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'lead_id',
        'bike_id',
        'motorcycle_id',
        'rental_unit_id',
        'public_catalog_location_id',
        'booking_number',
        'start_date',
        'end_date',
        'start_at',
        'end_at',
        'status',
        'price_per_day_snapshot',
        'total_price',
        'pricing_snapshot_json',
        'pricing_snapshot_schema_version',
        'currency',
        'rental_total_minor',
        'deposit_amount_minor',
        'payable_now_minor',
        'selected_tariff_id',
        'selected_tariff_kind',
        'deposit_amount',
        'payment_status',
        'customer_name',
        'phone',
        'preferred_contact_channel',
        'preferred_contact_value',
        'visitor_contact_channels_json',
        'legal_acceptances_json',
        'phone_normalized',
        'source',
        'customer_comment',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'status' => BookingStatus::class,
        'price_per_day_snapshot' => 'integer',
        'total_price' => 'integer',
        'deposit_amount' => 'integer',
        'pricing_snapshot_json' => 'array',
        'pricing_snapshot_schema_version' => 'integer',
        'visitor_contact_channels_json' => 'array',
        'legal_acceptances_json' => 'array',
        'rental_total_minor' => 'integer',
        'deposit_amount_minor' => 'integer',
        'payable_now_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = 'BK-'.strtoupper(Str::random(8));
            }
        });

        static::updated(function (Booking $booking) {
            if ($booking->status === BookingStatus::CANCELLED) {
                app(AvailabilityService::class)->unblockForBooking($booking);
            }

            if ($booking->wasChanged('status') && $booking->status === BookingStatus::CANCELLED) {
                $bookingId = (int) $booking->id;
                $tenantId = (int) $booking->tenant_id;
                DB::afterCommit(function () use ($bookingId, $tenantId): void {
                    $fresh = Booking::query()->find($bookingId);
                    $tenant = Tenant::query()->find($tenantId);
                    if ($fresh === null || $tenant === null) {
                        return;
                    }

                    $payload = app(BookingNotificationPresenter::class)->payloadForCancelled($tenant, $fresh);
                    app(NotificationEventRecorder::class)->record(
                        $tenantId,
                        'booking.cancelled',
                        class_basename(Booking::class),
                        $bookingId,
                        $payload,
                    );
                });
            }
        });
    }

    public function bike(): BelongsTo
    {
        return $this->belongsTo(Bike::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function rentalUnit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    /**
     * @return BelongsTo<TenantLocation, $this>
     */
    public function publicCatalogLocation(): BelongsTo
    {
        return $this->belongsTo(TenantLocation::class, 'public_catalog_location_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    /**
     * Статусы брони, при которых слот считается занятым для публичной доступности.
     *
     * @return list<BookingStatus>
     */
    public static function occupyingStatuses(): array
    {
        return [
            BookingStatus::PENDING,
            BookingStatus::AWAITING_PAYMENT,
            BookingStatus::CONFIRMED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function occupyingStatusValues(): array
    {
        return array_map(
            static fn (BookingStatus $s): string => $s->value,
            self::occupyingStatuses(),
        );
    }
}
