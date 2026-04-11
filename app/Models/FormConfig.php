<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'form_key',
        'title',
        'description',
        'is_enabled',
        'recipient_email',
        'success_message',
        'error_message',
        'fields_json',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'fields_json' => 'array',
            'settings_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function findEnabledForTenant(int $tenantId, string $formKey): ?self
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('form_key', $formKey)
            ->where('is_enabled', true)
            ->first();
    }
}
