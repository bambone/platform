<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('host')->unique(); // subdomain or custom domain
            $table->string('type')->default('subdomain'); // subdomain, custom
            $table->boolean('is_primary')->default(false);
            $table->string('ssl_status')->nullable(); // pending, active, failed
            $table->string('verification_status')->nullable(); // pending, verified, failed
            $table->string('dns_target')->nullable(); // CNAME target for verification
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
