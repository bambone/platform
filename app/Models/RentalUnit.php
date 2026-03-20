<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalUnit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'motorcycle_id',
        'integration_id',
        'external_id',
        'status',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public static function statuses(): array
    {
        return [
            'active' => 'Активна',
            'inactive' => 'Неактивна',
            'maintenance' => 'На обслуживании',
        ];
    }
}
