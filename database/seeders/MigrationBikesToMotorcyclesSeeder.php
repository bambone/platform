<?php

namespace Database\Seeders;

use App\Models\Bike;
use App\Models\Motorcycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MigrationBikesToMotorcyclesSeeder extends Seeder
{
    public function run(): void
    {
        Bike::withTrashed()->each(function (Bike $bike) {
            $slug = Str::slug($bike->name).'-'.$bike->id;
            $motorcycle = Motorcycle::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $bike->name,
                    'slug' => $slug,
                    'brand' => null,
                    'model' => $bike->type,
                    'category_id' => null,
                    'short_description' => null,
                    'full_description' => null,
                    'price_per_day' => $bike->price_per_day,
                    'price_2_3_days' => null,
                    'price_week' => null,
                    'status' => $bike->is_active ? 'available' : 'hidden',
                    'cover_image' => $bike->image,
                    'engine_cc' => $bike->engine,
                    'power' => null,
                    'transmission' => null,
                    'year' => null,
                    'mileage' => null,
                    'specs_json' => null,
                    'tags_json' => null,
                    'sort_order' => $bike->id,
                    'show_on_home' => $bike->is_active,
                    'show_in_catalog' => $bike->is_active,
                    'is_recommended' => false,
                ]
            );

            if ($bike->trashed()) {
                $motorcycle->delete();
            }
        });
    }
}
