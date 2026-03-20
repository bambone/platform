<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;

class TenantResolver
{
    public function __construct(
        protected CurrentTenantManager $manager
    ) {}

    /**
     * Resolve tenant from request host.
     * 4 modes: Platform host, Tenant admin, Tenant public, Unknown.
     */
    public function resolve(string $host): ?Tenant
    {
        if ($this->manager->isResolved()) {
            return $this->manager->getTenant();
        }

        $host = strtolower(explode(':', $host)[0]);
        $domain = TenantDomain::where('host', $host)->first();

        if ($domain) {
            $this->manager->setTenant($domain->tenant);

            return $domain->tenant;
        }

        $this->manager->setTenant(null);

        return null;
    }

    public function isPlatformHost(string $host): bool
    {
        $platformHost = config('app.platform_host', 'platform.motolevins.local');

        return $host === $platformHost || str_starts_with($host, 'platform.');
    }

    public function getPlatformHosts(): array
    {
        return [
            config('app.platform_host', 'platform.motolevins.local'),
        ];
    }
}
