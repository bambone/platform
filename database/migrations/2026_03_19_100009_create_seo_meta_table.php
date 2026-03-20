<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('h1')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->nullable();
            $table->string('twitter_card')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->boolean('is_followable')->default(true);
            $table->json('json_ld')->nullable();
            $table->timestamps();

            $table->index(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
