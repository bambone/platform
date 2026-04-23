<?php

use Database\Seeders\Tenant\BlackDuckBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Black Duck: IA Q1, scheduling demo, квота. См. {@see BlackDuckBootstrap::run()} и
 * {@see BlackDuckBootstrap::synchronizeCanonicalDomain()}.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new BlackDuckBootstrap)->run();
    }

    public function down(): void
    {
        BlackDuckBootstrap::rollback();
    }
};
