<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            TemplatePresetSeeder::class,
            MotoLevinsTenantSeeder::class,
            AdminUserSeeder::class, // includes RolePermissionSeeder; tenant must exist
            BikeSeeder::class,
            MigrationBikesToMotorcyclesSeeder::class,
            BackfillMotorcyclesDataSeeder::class,
            PagesAndSectionsSeeder::class,
            SettingsSeeder::class,
            FaqSeeder::class,
            ReviewSeeder::class,
            IntegrationSeeder::class,
        ]);
    }
}
