<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('domain_localization_presets')
            || ! DB::getSchemaBuilder()->hasTable('tenants')) {
            return;
        }

        $genericId = DB::table('domain_localization_presets')->where('slug', 'generic_services')->value('id');
        if ($genericId === null) {
            return;
        }

        DB::table('tenants')
            ->whereNull('domain_localization_preset_id')
            ->update(['domain_localization_preset_id' => $genericId]);
    }

    public function down(): void
    {
        //
    }
};
