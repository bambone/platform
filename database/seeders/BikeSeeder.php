<?php

namespace Database\Seeders;

use App\Models\Bike;
use Illuminate\Database\Seeder;

class BikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bikes = [
            ['name' => 'HONDA VFR 800F', 'type' => 'Спорт-турист', 'engine' => 800, 'price_per_day' => 9000, 'image' => 'bikes/honda_vfr_800f.jpg'],
            ['name' => 'HONDA CB 300R', 'type' => 'Нейкед', 'engine' => 300, 'price_per_day' => 4000, 'image' => 'bikes/honda_cb_300r.jpg'],
            ['name' => 'HONDA NC 750 INTEGRA', 'type' => 'Максискутер', 'engine' => 750, 'price_per_day' => 7500, 'image' => 'bikes/honda_nc_750_integra.jpg'],
            ['name' => 'HONDA СТХ 1300', 'type' => 'Круизер', 'engine' => 1300, 'price_per_day' => 10000, 'image' => 'bikes/honda_ctx_1300.jpg'],
            ['name' => 'HONDA NC 750S', 'type' => 'Дорожный', 'engine' => 750, 'price_per_day' => 5000, 'image' => 'bikes/honda_nc_750s.jpg'],
            ['name' => 'HONDA NC 750X (Красный)', 'type' => 'Турэндуро', 'engine' => 750, 'price_per_day' => 6500, 'image' => 'bikes/honda_nc_750x_red.jpg'],
            ['name' => 'HONDA NC 750X (Белый)', 'type' => 'Турэндуро', 'engine' => 750, 'price_per_day' => 6500, 'image' => 'bikes/honda_nc_750x_white.jpg'],
            ['name' => 'HONDA CTX 700', 'type' => 'Круизер', 'engine' => 700, 'price_per_day' => 6500, 'image' => 'bikes/honda_ctx_700.jpg'],
        ];

        foreach ($bikes as $bike) {
            Bike::create($bike);
        }
    }
}
