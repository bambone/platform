<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->text('text');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('avatar')->nullable();
            $table->json('photos_json')->nullable();
            $table->foreignId('motorcycle_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('published'); // draft, published, hidden
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
