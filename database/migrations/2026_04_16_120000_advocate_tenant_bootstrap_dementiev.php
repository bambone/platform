<?php

use Database\Seeders\Tenant\DementievAdvocateBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Advocate tenant dementiev: idempotent seed (theme advocate_editorial).
 * Test hosts include dementiev.local (see seeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        DementievAdvocateBootstrap::run();
    }

    public function down(): void
    {
        DementievAdvocateBootstrap::rollback();
    }
};
