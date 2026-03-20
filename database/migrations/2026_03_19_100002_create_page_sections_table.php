<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('section_key'); // hero, route_cards, fleet_block, why_us, how_it_works, rental_conditions, reviews_block, faq_block, final_cta
            $table->string('section_type')->nullable();
            $table->string('title')->nullable();
            $table->json('data_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('status')->default('published'); // draft, published, hidden
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['page_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
