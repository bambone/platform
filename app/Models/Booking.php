<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Services\AvailabilityService;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'booking_number',
        'start_date',
        'end_date',
        'start_at',
        'end_at',
        'status',
        'price_per_day_snapshot',
        'total_price',
        'pricing_snapshot_json',
        'deposit_amount',
        'payment_status',
        'customer_name',
        'phone',
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

    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }
}
