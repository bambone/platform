<?php

namespace App\Models;

use App\Scheduling\Enums\IntegrationErrorPolicy;
use App\Scheduling\Enums\StaleBusyPolicy;
use App\Terminology\TenantTerminologyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'domain_localization_preset_id',
        'owner_user_id',
        'support_manager_id',
        'mail_rate_limit_per_minute',
        'scheduling_module_enabled',
        'calendar_integrations_enabled',
        'scheduling_promo_free_until',
        'scheduling_integration_error_policy',
        'scheduling_stale_busy_policy',
        'scheduling_default_write_calendar_subscription_id',
        'media_write_mode_override',
        'media_delivery_mode_override',
    ];

    protected function casts(): array
    {
        return [
            'scheduling_module_enabled' => 'boolean',
            'calendar_integrations_enabled' => 'boolean',
            'scheduling_promo_free_until' => 'date',
            'scheduling_integration_error_policy' => IntegrationErrorPolicy::class,
            'scheduling_stale_busy_policy' => StaleBusyPolicy::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function domainLocalizationPreset(): BelongsTo
    {
        return $this->belongsTo(DomainLocalizationPreset::class, 'domain_localization_preset_id');
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

    public function mailLogs(): HasMany
    {
        return $this->hasMany(TenantMailLog::class);
    }

    public function storageQuota(): HasOne
    {
        return $this->hasOne(TenantStorageQuota::class);
    }

    public function storageQuotaEvents(): HasMany
    {
        return $this->hasMany(TenantStorageQuotaEvent::class);
    }

    public function schedulingResourceTypeLabels(): HasMany
    {
        return $this->hasMany(SchedulingResourceTypeLabel::class);
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
     * Base URL for the tenant Filament cabinet (`/admin`), for copying from Platform Console.
     * Prefers an active domain (primary first), then any primary/first domain.
     */
    public function cabinetAdminUrl(): ?string
    {
        $active = $this->domains()
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->orderByDesc('is_primary')
            ->first();

        $domain = $active ?? $this->primaryDomain();

        if ($domain === null || $domain->host === null || trim((string) $domain->host) === '') {
            return null;
        }

        return 'https://'.strtolower(trim((string) $domain->host)).'/admin';
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

        // Плейсхолдер в админке платформы: каталог tenant/themes/auto без шаблонов — не использовать для рендера.
        if ($raw === 'auto') {
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

    protected static function booted(): void
    {
        static::saved(function (Tenant $tenant): void {
            if ($tenant->wasChanged(['domain_localization_preset_id', 'locale'])) {
                app(TenantTerminologyService::class)->forgetTenant($tenant->id);
            }
        });
    }
}
