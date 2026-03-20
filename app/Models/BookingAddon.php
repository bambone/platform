<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAddon extends Model
{
    protected $fillable = [
        'booking_id',
        'addon_id',
        'quantity',
        'price_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_snapshot' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
