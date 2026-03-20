<?php

namespace Database\Seeders;

use App\Models\TemplatePreset;
use Illuminate\Database\Seeder;

class TemplatePresetSeeder extends Seeder
{
    public function run(): void
    {
        if (TemplatePreset::where('slug', 'moto-rental')->exists()) {
            return;
        }

        TemplatePreset::create([
            'name' => 'Moto Rental',
            'slug' => 'moto-rental',
            'description' => 'Шаблон для аренды мотоциклов',
            'config_json' => [
                'theme' => [
                    'primary_color' => '#f59e0b',
                    'font_family' => 'Inter',
                ],
                'default_pages' => [
                    'home' => ['name' => 'Главная', 'template' => 'default', 'status' => 'published'],
                    'contacts' => ['name' => 'Контакты', 'template' => 'default', 'status' => 'published'],
                ],
                'behavior_flags' => [
                    'booking_widget_enabled' => true,
                    'reviews_enabled' => true,
                    'map_enabled' => true,
                    'blog_enabled' => false,
                ],
                'default_sections' => [
                    'home' => [
                        ['section_key' => 'hero', 'title' => 'Hero', 'data_json' => [
                            'heading' => 'Аренда мотоциклов',
                            'subheading' => 'от 4 000 ₽/сутки',
                            'description' => 'Без скрытых платежей, экипировка и страховка включены',
                        ], 'sort_order' => 0],
                        ['section_key' => 'route_cards', 'title' => 'Маршруты', 'data_json' => ['items' => []], 'sort_order' => 10],
                        ['section_key' => 'fleet_block', 'title' => 'Автопарк', 'data_json' => [
                            'heading' => 'Наш автопарк',
                            'subheading' => 'Выберите технику',
                        ], 'sort_order' => 20],
                        ['section_key' => 'why_us', 'title' => 'Почему мы', 'data_json' => ['items' => []], 'sort_order' => 30],
                        ['section_key' => 'how_it_works', 'title' => 'Как это работает', 'data_json' => ['items' => []], 'sort_order' => 40],
                        ['section_key' => 'rental_conditions', 'title' => 'Условия', 'data_json' => ['items' => []], 'sort_order' => 50],
                        ['section_key' => 'reviews_block', 'title' => 'Отзывы', 'data_json' => ['show_block' => true], 'sort_order' => 60],
                        ['section_key' => 'faq_block', 'title' => 'FAQ', 'data_json' => ['show_on_home' => true], 'sort_order' => 70],
                        ['section_key' => 'final_cta', 'title' => 'CTA', 'data_json' => [], 'sort_order' => 100],
                    ],
                ],
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
