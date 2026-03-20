<?php

namespace App\Models;

use App\Contracts\IntegrationContract;
use App\Integrations\RentProgIntegration;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'is_enabled',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class)->orderByDesc('created_at');
    }

    public function rentalUnits(): HasMany
    {
        return $this->hasMany(RentalUnit::class, 'integration_id');
    }

    public function getAdapter(): ?IntegrationContract
    {
        return match ($this->type) {
            'rentprog' => app(RentProgIntegration::class, ['integration' => $this]),
            default => null,
        };
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: (self::types()[$this->type] ?? $this->type);
    }

    public function log(string $action, string $status, ?string $requestData = null, ?string $responseData = null, ?string $errorMessage = null): void
    {
        $this->logs()->create([
            'action' => $action,
            'status' => $status,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'error_message' => $errorMessage,
        ]);
    }

    public static function types(): array
    {
        return [
            'rentprog' => 'RentProg',
        ];
    }
}
