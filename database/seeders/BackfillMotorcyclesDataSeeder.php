<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Motorcycle;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BackfillMotorcyclesDataSeeder extends Seeder
{
    /** Bike type -> category name mapping */
    private const TYPE_TO_CATEGORY = [
        'Спорт-турист' => 'Спорт-турист',
        'Нейкед' => 'Нейкед',
        'Максискутер' => 'Максискутер',
        'Круизер' => 'Круизер',
        'Дорожный' => 'Дорожный',
        'Турэндуро' => 'Турэндуро',
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'motolevins')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant motolevins not found. BackfillMotorcyclesDataSeeder skipped.');

            return;
        }

        foreach (self::TYPE_TO_CATEGORY as $name) {
            Category::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'sort_order' => 0,
                    'is_active' => true,
                ]
            );
        }

        Motorcycle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->each(function (Motorcycle $m) use ($tenant): void {
                $updates = [];
                $parts = explode(' ', $m->name, 2);
                $brand = $parts[0] ?? null;
                $modelFromName = $parts[1] ?? $m->name;

                if (empty($m->brand)) {
                    $updates['brand'] = $brand;
                }

                if (empty($m->category_id) && $m->model && array_key_exists($m->model, self::TYPE_TO_CATEGORY)) {
                    $category = Category::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('name', $m->model)
                        ->first();
                    if ($category) {
                        $updates['category_id'] = $category->id;
                    }
                }

                if ($m->model && array_key_exists($m->model, self::TYPE_TO_CATEGORY)) {
                    $updates['model'] = $modelFromName;
                } elseif (empty($m->model)) {
                    $updates['model'] = $modelFromName;
                }

                if ($updates !== []) {
                    $m->update($updates);
                }
            });
    }
}
