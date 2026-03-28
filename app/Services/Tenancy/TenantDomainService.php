<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenant\HostClassifier;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantDomainService
{
    public function __construct(
        protected HostClassifier $hostClassifier
    ) {}

    public function createDefaultSubdomain(Tenant $tenant, string $slug): TenantDomain
    {
        $root = (string) config('tenancy.root_domain', '');
        $fallbackHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $suffix = $root !== '' ? $root : ($fallbackHost ?: 'localhost');
        $host = TenantDomain::normalizeHost($slug.'.'.$suffix);

        $tenantAlreadyHasPrimary = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_primary', true)
            ->exists();

        return TenantDomain::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'host' => $host],
            [
                'type' => TenantDomain::TYPE_SUBDOMAIN,
                'is_primary' => ! $tenantAlreadyHasPrimary,
                'status' => TenantDomain::STATUS_ACTIVE,
                'verification_method' => null,
                'verification_token' => null,
                'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
                'verified_at' => now(),
                'activated_at' => now(),
            ]
        );
    }

    public function addCustomDomain(Tenant $tenant, string $domain): TenantDomain
    {
        $normalized = TenantDomain::normalizeHost($domain);

        if ($normalized === '') {
            throw ValidationException::withMessages(['domain' => __('Укажите домен.')]);
        }

        $this->assertDomainAttachable($tenant, $normalized);

        return TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $normalized,
            'type' => TenantDomain::TYPE_CUSTOM,
            'is_primary' => false,
            'status' => TenantDomain::STATUS_PENDING,
            'verification_method' => 'dns_txt',
            'verification_token' => $this->generateVerificationToken(),
            'ssl_status' => TenantDomain::SSL_PENDING,
        ]);
    }

    public function setPrimaryDomain(TenantDomain $target): void
    {
        TenantDomain::query()
            ->where('tenant_id', $target->tenant_id)
            ->update(['is_primary' => false]);

        $target->update(['is_primary' => true]);
    }

    public function generateVerificationToken(): string
    {
        return 'rb-'.Str::random(40);
    }

    public function assertDomainAttachable(Tenant $tenant, string $normalizedHost): void
    {
        if ($this->hostClassifier->isNonTenantHost($normalizedHost)) {
            throw ValidationException::withMessages([
                'domain' => __('Этот адрес зарезервирован платформой и не может быть подключён как домен клиента.'),
            ]);
        }

        $root = TenantDomain::normalizeHost((string) config('tenancy.root_domain', ''));
        if ($root !== '' && str_ends_with($normalizedHost, '.'.$root)) {
            throw ValidationException::withMessages([
                'domain' => 'Поддомены платформы (*.'.$root.') выдаются автоматически; укажите свой домен.',
            ]);
        }

        $existing = TenantDomain::query()->where('host', $normalizedHost)->first();

        if ($existing !== null && $existing->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'domain' => __('Домен уже подключён к другому клиенту (domain already attached).'),
            ]);
        }

        if ($existing !== null && $existing->tenant_id === $tenant->id) {
            throw ValidationException::withMessages([
                'domain' => __('Этот домен уже добавлен для вашего аккаунта.'),
            ]);
        }
    }
}
