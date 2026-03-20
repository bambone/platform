<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'rental_unit_id')) {
                $table->foreignId('rental_unit_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'motorcycle_id')) {
                $table->foreignId('motorcycle_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'lead_id')) {
                $table->dropConstrainedForeignId('lead_id');
            }
            if (Schema::hasColumn('bookings', 'rental_unit_id')) {
                $table->dropConstrainedForeignId('rental_unit_id');
            }
            if (Schema::hasColumn('bookings', 'motorcycle_id')) {
                $table->dropConstrainedForeignId('motorcycle_id');
            }
        });
    }
};
