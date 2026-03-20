<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('status')->default('active');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'external_id']);
            $table->index(['motorcycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_units');
    }
};
