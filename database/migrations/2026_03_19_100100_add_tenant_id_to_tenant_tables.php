<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenantTables = [
            'pages',
            'page_sections',
            'categories',
            'motorcycles',
            'leads',
            'reviews',
            'faqs',
            'seo_meta',
            'redirects',
            'integrations',
            'integration_logs',
            'form_configs',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasTable('rental_units') && ! Schema::hasColumn('rental_units', 'tenant_id')) {
            Schema::table('rental_units', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'tenant_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('bikes') && ! Schema::hasColumn('bikes', 'tenant_id')) {
            Schema::table('bikes', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tenantTables = [
            'pages',
            'page_sections',
            'categories',
            'motorcycles',
            'leads',
            'reviews',
            'faqs',
            'seo_meta',
            'redirects',
            'integrations',
            'integration_logs',
            'form_configs',
            'rental_units',
            'bookings',
            'bikes',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }
    }
};
