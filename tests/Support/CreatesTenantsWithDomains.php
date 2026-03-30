<?php

namespace Tests\Support;

use App\Models\CrmRequest;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenant\TenantResolver;
use Illuminate\Support\Facades\Cache;

trait CreatesTenantsWithDomains
{
    protected function tenancyRootDomain(): string
    {
        return trim((string) config('tenancy.root_domain', 'apex.test'));
    }

    /**
     * Full host for a tenant slug, e.g. ta + apex.test → ta.apex.test
     */
    protected function tenancyHostForSlug(string $slug): string
    {
        return TenantDomain::normalizeHost($slug.'.'.$this->tenancyRootDomain());
    }

    protected function flushTenantHostCache(?string $host = null): void
    {
        if ($host !== null) {
            app(TenantResolver::class)->forgetCacheForHost($host);

            return;
        }

        Cache::flush();
    }

    /**
     * Creates an active tenant with a subdomain under {@see tenancyRootDomain()}.
     */
    protected function createTenantWithActiveDomain(string $slug, array $tenantAttributes = []): Tenant
    {
        $tenant = Tenant::query()->create(array_merge([
            'name' => 'Tenant '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ], $tenantAttributes));

        $host = $this->tenancyHostForSlug($slug);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $host,
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        $this->flushTenantHostCache($host);

        return $tenant;
    }

    protected function makeCrmRequest(?int $tenantId, array $overrides = []): CrmRequest
    {
        return CrmRequest::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'name' => 'Test CRM contact',
            'phone' => '+79991112233',
            'email' => fake()->unique()->safeEmail(),
            'message' => 'Test message',
            'request_type' => 'test_request',
            'source' => 'test',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'priority' => CrmRequest::PRIORITY_NORMAL,
            'last_activity_at' => now(),
        ], $overrides));
    }
}
