<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PagesAndSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'motolevins')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant motolevins not found. PagesAndSectionsSeeder skipped.');

            return;
        }

        $home = Page::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'home',
            ],
            [
                'name' => 'Главная',
                'template' => 'default',
                'status' => 'published',
                'published_at' => now(),
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
                    'video_poster' => 'images/motolevins/marketing/hero-bg.png',
                    'video_src' => 'images/motolevins/videos/Moto_levins_1.mp4',
                ],
                'sort_order' => 0,
            ],
            [
                'section_key' => 'route_cards',
                'title' => 'Карточки маршрутов',
                'data_json' => [
                    'items' => [
                        ['title' => 'Побережье', 'description' => 'Серпантины и морской бриз', 'icon' => 'coast'],
                        ['title' => 'Город', 'description' => 'Динамика и стиль', 'icon' => 'city'],
                        ['title' => 'Трасса', 'description' => 'Дальний маршрут', 'icon' => 'touring'],
                    ],
                ],
                'sort_order' => 10,
            ],
            [
                'section_key' => 'why_us',
                'title' => 'Почему мы',
                'data_json' => [
                    'heading' => 'Почему выбирают нас',
                    'lead' => 'Работаем с 2024 года. Никаких компромиссов в качестве и безопасности.',
                    'items' => [
                        ['title' => 'Полностью обслуженный мотоцикл', 'description' => 'Детейлинг и ТО перед каждой выдачей — без риска поломки в дороге. Вы едете спокойно.'],
                        ['title' => 'Прозрачные условия', 'description' => 'Цена в договоре = цена по факту. Полная страховка без скрытых доплат. КАСКО без франшизы — опция при бронировании.'],
                        ['title' => 'Поддержка 24/7', 'description' => 'Попали в ситуацию? Мы на связи. Замена мотоцикла, консультация по маршруту — ответ в течение 15 минут.'],
                        ['title' => 'Экипировка включена', 'description' => 'Шлемы и базовая экипировка — чистая, продезинфицированная. Не везите с собой — получите при выдаче.'],
                    ],
                ],
                'sort_order' => 30,
            ],
            [
                'section_key' => 'how_it_works',
                'title' => 'Как это работает',
                'data_json' => [
                    'lead' => 'Весь процесс занимает не более 15 минут. Четыре шага — и вы в пути.',
                    'items' => [
                        ['step' => 1, 'title' => 'Выберите байк', 'description' => 'Модель + даты. Всё.'],
                        ['step' => 2, 'title' => 'Оставьте заявку', 'description' => 'Имя, телефон, даты — 2 минуты.'],
                        ['step' => 3, 'title' => 'Бронь подтверждена', 'description' => 'Менеджер свяжется в течение 10 минут.'],
                        ['step' => 4, 'title' => 'Ключ на старт', 'description' => 'Чистый байк, полный бак — в путь.'],
                    ],
                ],
                'sort_order' => 40,
            ],
            [
                'section_key' => 'rental_conditions',
                'title' => 'Условия аренды',
                'data_json' => [
                    'items' => [
                        ['title' => 'Возраст', 'description' => 'От 21 года', 'badge' => '21+'],
                        ['title' => 'Стаж', 'description' => 'От 2 лет по категории А'],
                        ['title' => 'Документы', 'description' => 'Паспорт + права категории А'],
                        ['title' => 'Залог', 'description' => '30 000–80 000 ₽, возврат при сдаче'],
                        ['title' => 'Страховка', 'description' => 'ОСАГО + КАСКО без франшизы (опция)'],
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
                    'heading' => 'Забронируйте мотоцикл и отправляйтесь в поездку уже сегодня',
                    'description' => 'Экипировка включена. Цена фиксирована. Ограниченное количество техники — не откладывайте.',
                    'button_text' => 'Забронировать',
                ],
                'sort_order' => 80,
            ],
        ];

        foreach ($sections as $data) {
            PageSection::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'page_id' => $home->id,
                    'section_key' => $data['section_key'],
                ],
                array_merge($data, [
                    'tenant_id' => $tenant->id,
                    'status' => 'published',
                    'is_visible' => true,
                ])
            );
        }
    }
}
