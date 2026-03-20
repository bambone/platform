<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('action')->nullable();
            $table->string('status')->nullable(); // success, error
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
