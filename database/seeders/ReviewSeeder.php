<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'motolevins')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant motolevins not found. ReviewSeeder skipped.');

            return;
        }

        $items = [
            [
                'name' => 'Алексей М.',
                'city' => 'Геленджик',
                'text' => 'Огонь! Выдали за 10 минут, шлемы новые. Закат на побережье — разрыв.',
                'rating' => 5,
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 0,
                'avatar' => 'images/motolevins/avatars/avatar-1.png',
            ],
            [
                'name' => 'Игорь С.',
                'city' => 'Анапа',
                'text' => 'Никаких доплат по факту. Мот бодрый, тормоза цепкие. Следующий раз — на неделю.',
                'rating' => 5,
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 10,
                'avatar' => 'images/motolevins/avatars/avatar-2.png',
            ],
            [
                'name' => 'Анна В.',
                'city' => 'Новороссийск',
                'text' => 'Пригнали к отелю, сдали там же. Мотик ухоженный. Абрау-Дюрсо на закате — нечто.',
                'rating' => 5,
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 20,
                'avatar' => 'images/motolevins/avatars/avatar-3.png',
            ],
        ];

        foreach ($items as $item) {
            Review::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $item['name'],
                    'text' => $item['text'],
                ],
                array_merge($item, ['tenant_id' => $tenant->id])
            );
        }
    }
}
