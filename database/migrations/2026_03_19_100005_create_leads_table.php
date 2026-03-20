<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('messenger')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('motorcycle_id')->nullable()->constrained()->nullOnDelete();
            $table->date('rental_date_from')->nullable();
            $table->date('rental_date_to')->nullable();
            $table->string('source')->nullable(); // booking_form, contact_form, hero_cta_form, etc.
            $table->string('page_url')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('status')->default('new'); // new, in_progress, confirmed, cancelled, completed, spam
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manager_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
