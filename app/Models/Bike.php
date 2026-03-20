<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BikeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bike extends Model
{
    /** @use HasFactory<BikeFactory> */
    use BelongsToTenant, HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'engine',
        'price_per_day',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'engine' => 'integer',
        'price_per_day' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
