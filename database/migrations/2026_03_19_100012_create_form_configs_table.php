<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_configs', function (Blueprint $table) {
            $table->id();
            $table->string('form_key')->unique(); // booking_form, contact_form, hero_cta_form
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('recipient_email')->nullable();
            $table->text('success_message')->nullable();
            $table->text('error_message')->nullable();
            $table->json('fields_json')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_configs');
    }
};
