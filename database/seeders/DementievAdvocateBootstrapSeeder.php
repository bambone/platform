<?php

namespace Database\Seeders;

use Database\Seeders\Tenant\DementievAdvocateBootstrap;
use Illuminate\Database\Seeder;

/**
 * Обёртка для {@see DementievAdvocateBootstrap}: artisan принимает только классы, расширяющие {@see Seeder}.
 *
 * Запуск: {@code php artisan db:seed --class=DementievAdvocateBootstrapSeeder}
 */
class DementievAdvocateBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        DementievAdvocateBootstrap::run();
    }
}
