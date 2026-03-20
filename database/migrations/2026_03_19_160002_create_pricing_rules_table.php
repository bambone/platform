<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('motorcycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rental_type')->default('daily'); // hourly, daily, weekly
            $table->string('season')->nullable(); // high, low, default
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0-6, null = any
            $table->unsignedInteger('min_duration')->default(1); // days or hours
            $table->unsignedInteger('max_duration')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('deposit')->default(0);
            $table->unsignedInteger('insurance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // higher = applied first
            $table->timestamps();

            $table->index(['tenant_id', 'motorcycle_id', 'rental_type']);
            $table->index(['tenant_id', 'rental_unit_id', 'rental_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
