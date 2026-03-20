<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
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
            if (! TenantDomain::where('host', $host)->exists()) {
                TenantDomain::create([
                    'tenant_id' => $tenant->id,
                    'host' => $host,
                    'type' => 'subdomain',
                    'is_primary' => $index === 0 && ! $tenant->domains()->exists(),
                    'verification_status' => 'verified',
                ]);
            }
        }

        $ownerId = $tenant->owner_user_id;
        if ($ownerId && ! $tenant->users()->where('user_id', $ownerId)->exists()) {
            $tenant->users()->attach($ownerId, [
                'role' => 'tenant_owner',
                'status' => 'active',
            ]);
        }
    }
}
