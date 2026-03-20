<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('optional'); // optional, required
            $table->unsignedInteger('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('price_snapshot'); // price at booking time
            $table->timestamps();

            $table->unique(['booking_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_addons');
        Schema::dropIfExists('addons');
    }
};
