<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'motorcycle_id',
        'rental_unit_id',
        'rental_type',
        'season',
        'day_of_week',
        'min_duration',
        'max_duration',
        'price',
        'deposit',
        'insurance',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'price' => 'integer',
        'deposit' => 'integer',
        'insurance' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function rentalUnit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public static function rentalTypes(): array
    {
        return [
            'hourly' => 'Почасово',
            'daily' => 'Посуточно',
            'weekly' => 'Недельно',
        ];
    }
}
