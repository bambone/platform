<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;

/**
 * Обёртка: {@see AflyatunovExpertBootstrap} не наследует {@see Seeder}, чтобы {@see DatabaseSeeder} вызывал единообразно {@code $this->call(...)}.
 */
final class AflyatunovExpertBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        AflyatunovExpertBootstrap::run();
    }
}
