<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'theme_key',
        'legal_name',
        'brand_name',
        'status',
        'timezone',
        'locale',
        'country',
        'currency',
        'plan_id',
        'owner_user_id',
        'support_manager_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function supportManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_manager_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role', 'status', 'invited_at')
            ->withTimestamps();
    }

    public function primaryDomain(): ?TenantDomain
    {
        return $this->domains()->where('is_primary', true)->first()
            ?? $this->domains()->first();
    }

    /**
     * Safe theme directory key for view resolution (not derived from slug).
     * Invalid or empty DB values normalize to "default".
     */
    public function themeKey(): string
    {
        $raw = $this->theme_key;
        if ($raw === null || $raw === '') {
            return 'default';
        }

        $raw = strtolower(trim((string) $raw));
        if ($raw === '') {
            return 'default';
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $raw)) {
            return 'default';
        }

        return $raw;
    }

    public static function statuses(): array
    {
        return [
            'trial' => 'Пробный',
            'active' => 'Активен',
            'suspended' => 'Приостановлен',
            'archived' => 'В архиве',
        ];
    }
}
