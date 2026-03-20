<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Алексей М.',
                'city' => 'Геленджик',
                'text' => 'Огонь! Выдали за 10 минут, шлемы новые. Закат на побережье — разрыв.',
                'rating' => 5,
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Игорь С.',
                'city' => 'Анапа',
                'text' => 'Никаких доплат по факту. Мот бодрый, тормоза цепкие. Следующий раз — на неделю.',
                'rating' => 5,
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Анна В.',
                'city' => 'Новороссийск',
                'text' => 'Пригнали к отелю, сдали там же. Мотик ухоженный. Абрау-Дюрсо на закате — нечто.',
                'rating' => 5,
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 20,
            ],
        ];

        foreach ($items as $item) {
            Review::firstOrCreate(
                [
                    'name' => $item['name'],
                    'text' => $item['text'],
                ],
                $item
            );
        }
    }
}
