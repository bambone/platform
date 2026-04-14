<?php

namespace Database\Seeders;

use App\Models\TemplatePreset;
use Illuminate\Database\Seeder;

/**
 * Пресеты для мастера «Новый клиент» и привязки в карточке клиента.
 * Используем {@see firstOrCreate} по slug — не затираем правки из админки при повторном seed.
 */
class TemplatePresetSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMotoRental();
        $this->seedExpertAutoStarter();
        $this->seedAdvocateEditorialStarter();
    }

    private function seedMotoRental(): void
    {
        TemplatePreset::firstOrCreate(
            ['slug' => 'moto-rental'],
            [
                'name' => 'Moto Rental',
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
            ]
        );
    }

    private function seedExpertAutoStarter(): void
    {
        TemplatePreset::firstOrCreate(
            ['slug' => 'expert-auto-starter'],
            [
                'name' => 'Expert / услуги (expert_auto)',
                'description' => 'Старт под экспертный лендинг; в карточке клиента выставьте тему expert_auto.',
                'config_json' => [
                    'theme' => [
                        'theme_key_hint' => 'expert_auto',
                        'primary_color' => '#f59e0b',
                        'font_family' => 'Inter',
                    ],
                    'default_pages' => [
                        'home' => ['name' => 'Главная', 'template' => 'default', 'status' => 'published'],
                        'contacts' => ['name' => 'Контакты', 'template' => 'default', 'status' => 'published'],
                    ],
                    'behavior_flags' => [
                        'booking_widget_enabled' => false,
                        'reviews_enabled' => true,
                        'map_enabled' => true,
                        'blog_enabled' => false,
                    ],
                    'default_sections' => [
                        'home' => [
                            [
                                'section_key' => 'expert_hero',
                                'section_type' => 'expert_hero',
                                'title' => 'Hero',
                                'data_json' => [
                                    'heading' => 'Имя и специализация',
                                    'subheading' => 'Кратко: для кого вы и чем помогаете.',
                                    'primary_cta_label' => 'Связаться',
                                    'primary_cta_anchor' => '#expert-inquiry',
                                ],
                                'sort_order' => 0,
                            ],
                            [
                                'section_key' => 'expert_lead_form',
                                'section_type' => 'expert_lead_form',
                                'title' => 'Заявка',
                                'data_json' => [
                                    'heading' => 'Оставить заявку',
                                    'form_key' => 'expert_lead',
                                    'section_id' => 'expert-inquiry',
                                ],
                                'sort_order' => 100,
                            ],
                        ],
                        'contacts' => [
                            [
                                'section_key' => 'main',
                                'section_type' => 'rich_text',
                                'title' => 'Контакты',
                                'data_json' => [
                                    'content' => '<p>Укажите адрес и способы связи. Настройте формы в кабинете клиента.</p>',
                                ],
                                'sort_order' => 0,
                            ],
                        ],
                    ],
                ],
                'sort_order' => 2,
                'is_active' => true,
            ]
        );
    }

    private function seedAdvocateEditorialStarter(): void
    {
        TemplatePreset::firstOrCreate(
            ['slug' => 'advocate-editorial-starter'],
            [
                'name' => 'Адвокат / персональный бренд (advocate_editorial)',
                'description' => 'Старт под юридический / экспертный сайт; в карточке клиента выставьте тему advocate_editorial.',
                'config_json' => [
                    'theme' => [
                        'theme_key_hint' => 'advocate_editorial',
                        'primary_color' => '#9a7b4f',
                        'font_family' => 'Inter',
                    ],
                    'default_pages' => [
                        'home' => ['name' => 'Главная', 'template' => 'default', 'status' => 'published'],
                        'contacts' => ['name' => 'Контакты', 'template' => 'default', 'status' => 'published'],
                    ],
                    'behavior_flags' => [
                        'booking_widget_enabled' => false,
                        'reviews_enabled' => false,
                        'map_enabled' => true,
                        'blog_enabled' => false,
                    ],
                    'default_sections' => [
                        'home' => [
                            [
                                'section_key' => 'expert_hero',
                                'section_type' => 'expert_hero',
                                'title' => 'Hero',
                                'data_json' => [
                                    'heading' => 'ФИО, статус',
                                    'subheading' => 'Позиционирование и география приёма.',
                                    'primary_cta_label' => 'Консультация',
                                    'primary_cta_anchor' => '#expert-inquiry',
                                ],
                                'sort_order' => 0,
                            ],
                            [
                                'section_key' => 'expert_lead_form',
                                'section_type' => 'expert_lead_form',
                                'title' => 'Запрос консультации',
                                'data_json' => [
                                    'heading' => 'Связаться',
                                    'form_key' => 'expert_lead',
                                    'section_id' => 'expert-inquiry',
                                ],
                                'sort_order' => 100,
                            ],
                        ],
                        'contacts' => [
                            [
                                'section_key' => 'main',
                                'section_type' => 'rich_text',
                                'title' => 'Контакты',
                                'data_json' => [
                                    'content' => '<p>Адрес, телефон, e-mail и мессенджеры — заполните в кабинете и в настройках контактов.</p>',
                                ],
                                'sort_order' => 0,
                            ],
                        ],
                    ],
                ],
                'sort_order' => 3,
                'is_active' => true,
            ]
        );
    }
}
