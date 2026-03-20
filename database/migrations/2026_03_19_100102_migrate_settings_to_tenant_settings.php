<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('tenant_settings')) {
            return;
        }

        $tenantId = DB::table('tenants')->where('slug', 'motolevins')->value('id');
        if (! $tenantId) {
            return;
        }

        $settings = DB::table('settings')->get();
        foreach ($settings as $setting) {
            DB::table('tenant_settings')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'group' => $setting->group,
                    'key' => $setting->key,
                ],
                [
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        //
    }
};
