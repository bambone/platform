<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = $this->ensureMotoLevinsTenantExists();

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
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable(false)->change();
                });
            }
        }

        $this->updateUniqueConstraints($tenantId);
    }

    protected function ensureMotoLevinsTenantExists(): int
    {
        $tenant = DB::table('tenants')->where('slug', 'motolevins')->first();

        if ($tenant) {
            return $tenant->id;
        }

        $planId = DB::table('plans')->first()?->id;
        $ownerId = DB::table('users')->first()?->id;

        return DB::table('tenants')->insertGetId([
            'name' => 'Moto Levins',
            'slug' => 'motolevins',
            'brand_name' => 'Moto Levins',
            'status' => 'active',
            'timezone' => 'Europe/Moscow',
            'locale' => 'ru',
            'currency' => 'RUB',
            'plan_id' => $planId,
            'owner_user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function updateUniqueConstraints(int $tenantId): void
    {
        if (Schema::hasTable('pages')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->unique(['tenant_id', 'slug']);
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->unique(['tenant_id', 'slug']);
            });
        }

        if (Schema::hasTable('motorcycles')) {
            Schema::table('motorcycles', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->unique(['tenant_id', 'slug']);
            });
        }

        if (Schema::hasTable('redirects')) {
            Schema::table('redirects', function (Blueprint $table) {
                $table->dropUnique(['from_url']);
                $table->unique(['tenant_id', 'from_url']);
            });
        }

        if (Schema::hasTable('form_configs')) {
            Schema::table('form_configs', function (Blueprint $table) {
                $table->dropUnique(['form_key']);
                $table->unique(['tenant_id', 'form_key']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'slug']);
                $table->unique('slug');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'slug']);
                $table->unique('slug');
            });
        }

        if (Schema::hasTable('motorcycles')) {
            Schema::table('motorcycles', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'slug']);
                $table->unique('slug');
            });
        }

        if (Schema::hasTable('redirects')) {
            Schema::table('redirects', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'from_url']);
                $table->unique('from_url');
            });
        }

        if (Schema::hasTable('form_configs')) {
            Schema::table('form_configs', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'form_key']);
                $table->unique('form_key');
            });
        }

        $tenantTables = [
            'pages', 'page_sections', 'categories', 'motorcycles', 'leads',
            'reviews', 'faqs', 'seo_meta', 'redirects', 'integrations',
            'integration_logs', 'form_configs', 'rental_units', 'bookings', 'bikes',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->change();
                });
            }
        }
    }
};
