<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

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
     * Public site title fallback when tenant_settings.general.site_name is empty (not platform app name).
     */
    public function defaultPublicSiteName(): string
    {
        $brand = $this->brand_name;
        if (is_string($brand) && trim($brand) !== '') {
            return trim($brand);
        }

        return (string) $this->name;
    }

    /**
     * Public site base URL fallback when tenant_settings.general.domain is empty (not marketing APP_URL).
     * Prefers the active tenant domain that matches the current request host, then primary domain (https).
     */
    public function defaultPublicSiteUrl(?Request $request = null): string
    {
        $request ??= request();
        $currentHost = $request ? strtolower((string) $request->getHost()) : '';

        if ($currentHost !== '') {
            $match = $this->domains()
                ->where('status', TenantDomain::STATUS_ACTIVE)
                ->get()
                ->first(fn (TenantDomain $d): bool => strcasecmp((string) $d->host, $currentHost) === 0);
            if ($match !== null) {
                return $request->getScheme().'://'.$match->host;
            }
        }

        $primary = $this->primaryDomain();
        if ($primary !== null && filled($primary->host)) {
            return 'https://'.(string) $primary->host;
        }

        if ($request !== null && $currentHost !== '') {
            return $request->getScheme().'://'.$currentHost;
        }

        return rtrim((string) config('app.url'), '/');
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
