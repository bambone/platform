<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

class PagesAndSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $home = Page::firstOrCreate(
            ['slug' => 'home'],
            [
                'name' => 'Главная',
                'template' => 'default',
                'status' => 'published',
            ]
        );

        $sections = [
            [
                'section_key' => 'hero',
                'title' => 'Hero',
                'data_json' => [
                    'heading' => 'Аренда мотоциклов на Чёрном море',
                    'subheading' => 'от 4 000 ₽/сутки',
                    'description' => 'Геленджик · Анапа · Новороссийск — без скрытых платежей, экипировка и страховка включены',
                    'video_poster' => 'images/hero-bg.png',
                    'video_src' => 'videos/Moto_levins_1.mp4',
                ],
                'sort_order' => 0,
            ],
            [
                'section_key' => 'route_cards',
                'title' => 'Карточки маршрутов',
                'data_json' => [
                    'items' => [
                        ['title' => 'Геленджик — Анапа', 'description' => 'Живописная трасса вдоль моря', 'icon' => 'route'],
                        ['title' => 'Горные серпантины', 'description' => 'Маршруты в предгорья Кавказа', 'icon' => 'mountain'],
                        ['title' => 'Новороссийск', 'description' => 'Порт и окрестности', 'icon' => 'port'],
                    ],
                ],
                'sort_order' => 10,
            ],
            [
                'section_key' => 'why_us',
                'title' => 'Почему мы',
                'data_json' => [
                    'items' => [
                        ['title' => 'Без скрытых платежей', 'description' => 'Всё включено в цену'],
                        ['title' => 'Экипировка', 'description' => 'Шлемы и защита в подарок'],
                        ['title' => 'Страховка', 'description' => 'КАСКО на весь период аренды'],
                    ],
                ],
                'sort_order' => 30,
            ],
            [
                'section_key' => 'how_it_works',
                'title' => 'Как это работает',
                'data_json' => [
                    'items' => [
                        ['step' => 1, 'title' => 'Выберите мотоцикл', 'description' => 'Оформите заявку на сайте'],
                        ['step' => 2, 'title' => 'Подтверждение', 'description' => 'Менеджер свяжется с вами'],
                        ['step' => 3, 'title' => 'Получите технику', 'description' => 'В удобной точке выдачи'],
                    ],
                ],
                'sort_order' => 40,
            ],
            [
                'section_key' => 'rental_conditions',
                'title' => 'Условия аренды',
                'data_json' => [
                    'items' => [
                        ['title' => 'Права категории А', 'description' => 'Обязательно для управления'],
                        ['title' => 'Залог', 'description' => 'Возвращается при сдаче'],
                        ['title' => 'Пробег', 'description' => 'Безлимитный или по тарифу'],
                    ],
                ],
                'sort_order' => 50,
            ],
            [
                'section_key' => 'reviews_block',
                'title' => 'Блок отзывов',
                'data_json' => [
                    'show_block' => true,
                    'selected_review_ids' => [],
                    'heading' => 'Отзывы райдеров',
                    'subheading' => 'Реальные эмоции с южных трасс. Фото, имена, города.',
                ],
                'sort_order' => 60,
            ],
            [
                'section_key' => 'faq_block',
                'title' => 'Блок FAQ',
                'data_json' => [
                    'show_on_home' => true,
                    'heading' => 'Частые вопросы',
                    'subheading' => 'Всё, что нужно знать перед тем, как завести мотор.',
                ],
                'sort_order' => 70,
            ],
            [
                'section_key' => 'final_cta',
                'title' => 'Финальный CTA',
                'data_json' => [
                    'heading' => 'Готовы к поездке?',
                    'description' => 'Оставьте заявку — подберём идеальный мотоцикл',
                    'button_text' => 'Забронировать',
                ],
                'sort_order' => 80,
            ],
        ];

        foreach ($sections as $data) {
            PageSection::updateOrCreate(
                [
                    'page_id' => $home->id,
                    'section_key' => $data['section_key'],
                ],
                array_merge($data, ['status' => 'published', 'is_visible' => true])
            );
        }
    }
}
