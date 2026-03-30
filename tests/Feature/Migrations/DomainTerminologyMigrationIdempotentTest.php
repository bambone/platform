<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainTerminologyMigrationIdempotentTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_terminology_migration_up_can_run_twice_without_errors(): void
    {
        $migration = require database_path('migrations/2026_03_30_120000_create_domain_terminology_tables.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('domain_terms'));
        $this->assertTrue(Schema::hasTable('domain_localization_presets'));
        $this->assertTrue(Schema::hasColumn('tenants', 'domain_localization_preset_id'));
    }
}
