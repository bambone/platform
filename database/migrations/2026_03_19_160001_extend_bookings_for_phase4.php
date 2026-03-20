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
            if (! Schema::hasColumn('bookings', 'booking_number')) {
                $table->string('booking_number')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('bookings', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'rental_unit_id')) {
                $table->foreignId('rental_unit_id')->nullable()->after('bike_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'start_at')) {
                $table->dateTime('start_at')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('bookings', 'end_at')) {
                $table->dateTime('end_at')->nullable()->after('start_at');
            }
            if (! Schema::hasColumn('bookings', 'pricing_snapshot_json')) {
                $table->json('pricing_snapshot_json')->nullable()->after('total_price');
            }
            if (! Schema::hasColumn('bookings', 'deposit_amount')) {
                $table->unsignedInteger('deposit_amount')->default(0)->after('pricing_snapshot_json');
            }
            if (! Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('deposit_amount');
            }
            if (! Schema::hasColumn('bookings', 'notes')) {
                $table->text('notes')->nullable()->after('customer_comment');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'booking_number')) {
                $table->dropColumn('booking_number');
            }
            if (Schema::hasColumn('bookings', 'lead_id')) {
                $table->dropConstrainedForeignId('lead_id');
            }
            if (Schema::hasColumn('bookings', 'rental_unit_id')) {
                $table->dropConstrainedForeignId('rental_unit_id');
            }
            if (Schema::hasColumn('bookings', 'start_at')) {
                $table->dropColumn('start_at');
            }
            if (Schema::hasColumn('bookings', 'end_at')) {
                $table->dropColumn('end_at');
            }
            if (Schema::hasColumn('bookings', 'pricing_snapshot_json')) {
                $table->dropColumn('pricing_snapshot_json');
            }
            if (Schema::hasColumn('bookings', 'deposit_amount')) {
                $table->dropColumn('deposit_amount');
            }
            if (Schema::hasColumn('bookings', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('bookings', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
