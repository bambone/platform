<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('language', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality')->nullable();
            $table->json('driver_license_data')->nullable();
            $table->json('passport_data')->nullable();
            $table->text('address')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('lifetime_value')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
