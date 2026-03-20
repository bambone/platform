<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'bike_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('bike_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'bike_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('bike_id')->nullable(false)->change();
            });
        }
    }
};
