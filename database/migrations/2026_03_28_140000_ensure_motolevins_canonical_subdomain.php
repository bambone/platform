<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Tenancy\TenantDomainService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Production: motolevins.rentbase.su must exist in tenant_domains when TENANCY_ROOT_DOMAIN=rentbase.su.
     * Legacy seeders only added TENANT_DEFAULT_HOST (e.g. motolevins.local).
     */
    public function up(): void
    {
        $tenant = Tenant::query()->where('slug', 'motolevins')->first();

        if ($tenant === null) {
            return;
        }

        if ((string) config('tenancy.root_domain', '') === '') {
            return;
        }

        app(TenantDomainService::class)->createDefaultSubdomain($tenant, $tenant->slug);
    }

    public function down(): void
    {
        $tenant = Tenant::query()->where('slug', 'motolevins')->first();
        $root = (string) config('tenancy.root_domain', '');

        if ($tenant === null || $root === '') {
            return;
        }

        $host = TenantDomain::normalizeHost($tenant->slug.'.'.$root);

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('host', $host)
            ->delete();
    }
};
