<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->unsignedInteger('price_per_day')->default(0);
            $table->unsignedInteger('price_2_3_days')->nullable();
            $table->unsignedInteger('price_week')->nullable();
            $table->string('status')->default('available'); // available, hidden, maintenance, booked, archived
            $table->unsignedInteger('engine_cc')->nullable();
            $table->unsignedInteger('power')->nullable();
            $table->string('transmission')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->json('specs_json')->nullable(); // weight, seat_height, fuel_consumption, abs, equipment, luggage
            $table->json('tags_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_on_home')->default(false);
            $table->boolean('show_in_catalog')->default(true);
            $table->boolean('is_recommended')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycles');
    }
};
