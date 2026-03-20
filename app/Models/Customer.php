<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'full_name',
        'phone',
        'email',
        'language',
        'birth_date',
        'nationality',
        'driver_license_data',
        'passport_data',
        'address',
        'tags',
        'lifetime_value',
        'notes',
    ];

    protected $casts = [
        'driver_license_data' => 'array',
        'passport_data' => 'array',
        'tags' => 'array',
        'birth_date' => 'date',
        'lifetime_value' => 'integer',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
