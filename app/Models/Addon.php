<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function bookingAddons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    public static function types(): array
    {
        return [
            'optional' => 'Опционально',
            'required' => 'Обязательно',
        ];
    }
}
