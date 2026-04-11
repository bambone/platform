<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return;
        }
        if (Schema::hasColumn('tenant_service_programs', 'cover_image_ref')) {
            return;
        }
        Schema::table('tenant_service_programs', function (Blueprint $table): void {
            $table->string('cover_image_ref', 2048)->nullable()->after('outcomes_json');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return;
        }
        if (! Schema::hasColumn('tenant_service_programs', 'cover_image_ref')) {
            return;
        }
        Schema::table('tenant_service_programs', function (Blueprint $table): void {
            $table->dropColumn('cover_image_ref');
        });
    }
};
