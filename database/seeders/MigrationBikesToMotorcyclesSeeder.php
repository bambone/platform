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
        Bike::withoutGlobalScopes()->withTrashed()->each(function (Bike $bike): void {
            if (empty($bike->tenant_id)) {
                return;
            }

            $slug = Str::slug($bike->name).'-'.$bike->id;
            $image = $bike->image;
            if (is_string($image) && str_starts_with($image, 'bikes/')) {
                $image = 'motolevins/'.$image;
            }

            $motorcycle = Motorcycle::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $bike->tenant_id,
                    'slug' => $slug,
                ],
                [
                    'name' => $bike->name,
                    'brand' => null,
                    'model' => $bike->type,
                    'category_id' => null,
                    'short_description' => null,
                    'full_description' => null,
                    'price_per_day' => $bike->price_per_day,
                    'price_2_3_days' => null,
                    'price_week' => null,
                    'status' => $bike->is_active ? 'available' : 'hidden',
                    'cover_image' => $image,
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
