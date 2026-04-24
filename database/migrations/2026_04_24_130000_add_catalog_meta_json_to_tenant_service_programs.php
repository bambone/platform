<?php

declare(strict_types=1);

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
        if (Schema::hasColumn('tenant_service_programs', 'catalog_meta_json')) {
            return;
        }

        Schema::table('tenant_service_programs', function (Blueprint $table): void {
            $table->json('catalog_meta_json')->nullable()->after('cover_presentation_json');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return;
        }
        if (! Schema::hasColumn('tenant_service_programs', 'catalog_meta_json')) {
            return;
        }
        Schema::table('tenant_service_programs', function (Blueprint $table): void {
            $table->dropColumn('catalog_meta_json');
        });
    }
};

