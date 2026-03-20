<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_unit_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status'); // available, blocked, booked
            $table->string('source')->nullable(); // manual, booking, integration
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rental_unit_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_calendar');
    }
};
