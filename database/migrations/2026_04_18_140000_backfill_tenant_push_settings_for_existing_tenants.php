<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_push_settings') || ! Schema::hasTable('tenants')) {
            return;
        }

        $existing = DB::table('tenant_push_settings')->pluck('tenant_id')->all();
        $existingSet = array_fill_keys(array_map('intval', $existing), true);

        $tenantIds = DB::table('tenants')->pluck('id');

        $now = now();
        foreach ($tenantIds as $tenantId) {
            $tid = (int) $tenantId;
            if (isset($existingSet[$tid])) {
                continue;
            }

            DB::table('tenant_push_settings')->insert([
                'tenant_id' => $tid,
                'push_override' => 'inherit_plan',
                'self_serve_allowed' => true,
                'commercial_service_active' => false,
                'setup_status' => 'not_started',
                'provider_status' => 'not_configured',
                'onesignal_key_pending_verification' => false,
                'is_push_enabled' => false,
                'is_pwa_enabled' => false,
                'pwa_display' => 'standalone',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Данные не удаляем при откате миграции: строки могли появиться в проде.
    }
};
