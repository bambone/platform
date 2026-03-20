<?php

namespace Database\Seeders;

use App\Models\Integration;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    public function run(): void
    {
        Integration::firstOrCreate(
            ['type' => 'rentprog'],
            [
                'name' => 'RentProg',
                'is_enabled' => false,
                'config' => [
                    'api_key' => '',
                    'base_url' => '',
                ],
            ]
        );
    }
}
