<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityCalendar extends Model
{
    protected $table = 'availability_calendar';

    protected $fillable = [
        'rental_unit_id',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'booking_id',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function rentalUnit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statuses(): array
    {
        return [
            'available' => 'Доступен',
            'blocked' => 'Заблокирован',
            'booked' => 'Забронирован',
        ];
    }
}
