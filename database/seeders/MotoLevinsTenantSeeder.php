<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Tenancy\TenantDomainService;
use Illuminate\Database\Seeder;

class MotoLevinsTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'motolevins')->first();

        if (! $tenant) {
            return;
        }

        $hosts = array_filter([
            config('app.tenant_default_host'),
            app()->environment('local') ? 'localhost' : null,
            app()->environment('local') ? '127.0.0.1' : null,
        ]);

        foreach ($hosts as $index => $host) {
            $normalized = TenantDomain::normalizeHost((string) $host);
            if ($normalized === '') {
                continue;
            }

            if (TenantDomain::where('host', $normalized)->exists()) {
                continue;
            }

            TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $normalized,
                'type' => TenantDomain::TYPE_SUBDOMAIN,
                'is_primary' => $index === 0 && ! $tenant->domains()->exists(),
                'status' => TenantDomain::STATUS_ACTIVE,
                'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
                'verified_at' => now(),
                'activated_at' => now(),
            ]);
        }

        app(TenantDomainService::class)->createDefaultSubdomain($tenant, $tenant->slug);

        $ownerId = $tenant->owner_user_id;
        if ($ownerId && ! $tenant->users()->where('user_id', $ownerId)->exists()) {
            $tenant->users()->attach($ownerId, [
                'role' => 'tenant_owner',
                'status' => 'active',
            ]);
        }
    }
}
