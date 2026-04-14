<?php

namespace Database\Seeders;

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Database\Seeders\Tenant\DementievAdvocateBootstrap;
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
            DomainTerminologySeeder::class,
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

        // Демо-клиенты из bootstrap-миграций: без этого шага `php artisan db:seed` не создаёт/не досинхронизирует
        // тенантов, заведённых только миграциями — в консоли платформы (раздел «Клиенты») их не будет видно.
        DementievAdvocateBootstrap::run();
        AflyatunovExpertBootstrap::run();
    }
}
