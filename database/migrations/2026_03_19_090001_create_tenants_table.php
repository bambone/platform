<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('status')->default('trial'); // trial, active, suspended, archived
            $table->string('timezone')->default('Europe/Moscow');
            $table->string('locale', 10)->default('ru');
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
