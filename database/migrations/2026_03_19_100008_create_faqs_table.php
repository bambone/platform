<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('published'); // draft, published, hidden
            $table->boolean('show_on_home')->default(false);
            $table->timestamps();

            $table->index(['status', 'show_on_home']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
