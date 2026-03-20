<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tenant = DB::table('tenants')->where('slug', 'motolevins')->first();

        if (! $tenant) {
            return;
        }

        $isLocal = app()->environment('local');
        $hosts = array_filter([
            config('app.tenant_default_host', 'motolevins.local'),
            $isLocal ? 'localhost' : null,
            $isLocal ? '127.0.0.1' : null,
        ]);

        foreach ($hosts as $index => $host) {
            if (! DB::table('tenant_domains')->where('host', $host)->exists()) {
                DB::table('tenant_domains')->insert([
                    'tenant_id' => $tenant->id,
                    'host' => $host,
                    'type' => 'subdomain',
                    'is_primary' => $index === 0,
                    'verification_status' => 'verified',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $hosts = [
            config('app.tenant_default_host', 'motolevins.local'),
            'localhost',
            '127.0.0.1',
        ];

        DB::table('tenant_domains')->whereIn('host', $hosts)->delete();
    }
};
