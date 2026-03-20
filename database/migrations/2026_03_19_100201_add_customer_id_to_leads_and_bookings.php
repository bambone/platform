<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'customer_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->after('motorcycle_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'customer_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'customer_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_id');
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'customer_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_id');
            });
        }
    }
};
