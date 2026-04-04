<?php

namespace App\Models;

use App\Tenant\TenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class TenantDomain extends Model
{
    public const TYPE_SUBDOMAIN = 'subdomain';

    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const SSL_NOT_REQUIRED = 'not_required';

    public const SSL_PENDING = 'pending';

    public const SSL_ISSUED = 'issued';

    public const SSL_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'host',
        'type',
        'is_primary',
        'status',
        'ssl_status',
        'verification_method',
        'verification_token',
        'dns_target',
        'last_checked_at',
        'verified_at',
        'activated_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'last_checked_at' => 'datetime',
        'verified_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (TenantDomain $domain) {
            $domain->host = self::normalizeHost($domain->host);
        });

        static::saved(function (TenantDomain $domain) {
            self::forgetResolverCacheForModel($domain);
        });

        static::deleted(function (TenantDomain $domain) {
            self::forgetResolverCacheForModel($domain);
        });

        static::deleting(function (TenantDomain $domain) {
            $hasOtherDomains = self::query()
                ->where('tenant_id', $domain->tenant_id)
                ->whereKeyNot($domain->getKey())
                ->exists();

            if (! $hasOtherDomains) {
                return false;
            }

            if ($domain->is_primary) {
                $next = self::query()
                    ->where('tenant_id', $domain->tenant_id)
                    ->whereKeyNot($domain->getKey())
                    ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [self::STATUS_ACTIVE])
                    ->orderBy('id')
                    ->first();

                if ($next !== null) {
                    $next->forceFill(['is_primary' => true])->saveQuietly();
                }
            }
        });
    }

    public static function normalizeHost(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $host = mb_strtolower(trim($value));
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = preg_replace('#/.*$#', '', $host) ?? $host;

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return rtrim($host, '.');
    }

    /**
     * @deprecated Use normalizeHost() — same contract (scheme, path, port, trailing dot).
     */
    public static function normalizeDomain(string $host): string
    {
        return self::normalizeHost($host);
    }

    public static function forgetResolverCacheForHost(string $host): void
    {
        $normalized = self::normalizeHost($host);
        if ($normalized === '') {
            return;
        }

        Cache::forget(TenantResolver::tenantHostCacheKey($normalized));
    }

    protected static function forgetResolverCacheForModel(TenantDomain $domain): void
    {
        self::forgetResolverCacheForHost($domain->host);

        if ($domain->wasChanged('host') && $domain->getOriginal('host')) {
            self::forgetResolverCacheForHost((string) $domain->getOriginal('host'));
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCustom(): bool
    {
        return $this->type === self::TYPE_CUSTOM;
    }

    public function isSubdomain(): bool
    {
        return $this->type === self::TYPE_SUBDOMAIN;
    }

    public static function types(): array
    {
        return [
            'subdomain' => 'Поддомен',
            'custom' => 'Кастомный домен',
        ];
    }
}
