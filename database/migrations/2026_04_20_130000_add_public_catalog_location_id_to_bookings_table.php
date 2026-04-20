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

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'public_catalog_location_id')) {
                $table->foreignId('public_catalog_location_id')
                    ->nullable()
                    ->constrained('tenant_locations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'public_catalog_location_id')) {
                $table->dropConstrainedForeignId('public_catalog_location_id');
            }
        });
    }
};
