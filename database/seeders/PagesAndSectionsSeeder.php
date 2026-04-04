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
                'section_type' => 'hero',
                'title' => 'Hero',
                'data_json' => [
                    'heading' => 'Аренда мотоциклов на Чёрном море',
                    'subheading' => 'от 4 000 ₽/сутки',
                    'description' => 'Геленджик · Анапа · Новороссийск — без скрытых платежей, экипировка и страховка включены',
                    'video_poster' => 'images/motolevins/marketing/hero-bg.png',
                    'video_src' => 'site/videos/Moto_levins_1.mp4',
                ],
                'sort_order' => 0,
            ],
            [
                'section_key' => 'route_cards',
                'section_type' => 'features',
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
                'section_key' => 'motorcycle_catalog',
                'section_type' => 'motorcycle_catalog',
                'title' => 'Каталог мотоциклов',
                'data_json' => [
                    'heading' => 'Наш автопарк',
                    'subheading' => 'Премиальная техника для любого стиля. Ограниченное количество мотоциклов — бронируйте заранее.',
                ],
                'sort_order' => 25,
            ],
            [
                'section_key' => 'why_us',
                'section_type' => 'features',
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
                'section_type' => 'features',
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
                'section_type' => 'features',
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
                'section_type' => 'cards_teaser',
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
                'section_type' => 'faq',
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
                'section_type' => 'cta',
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

        $this->seedMotolevinsContactsPage($tenant);
        $this->seedMotolevinsRentalTermsPage($tenant);
    }

    private function seedMotolevinsContactsPage(Tenant $tenant): void
    {
        $page = Page::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'contacts',
            ],
            [
                'name' => 'Контакты',
                'template' => 'default',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        PageSection::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'page_id' => $page->id,
                'section_key' => 'main',
            ],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Вводный текст',
                'section_type' => 'rich_text',
                'data_json' => [
                    'content' => '<p>Ответим на вопросы по моделям, свободным датам и условиям аренды. Если уже выбрали байк — напишите или позвоните, согласуем выдачу и время встречи.</p><p><a href="/usloviya-arenda">Кратко о правилах и документах</a></p>',
                ],
                'sort_order' => 0,
                'status' => 'published',
                'is_visible' => true,
            ]
        );

        PageSection::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'page_id' => $page->id,
                'section_key' => 'contacts_primary',
            ],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Контакты и карта',
                'section_type' => 'contacts_info',
                'data_json' => [
                    'title' => 'Свяжитесь с нами',
                    'description' => 'Работаем круглый год. Напишите, когда вам удобно — подберём окно на выдачу и ответим по комплектации и маршрутам. Срочный вопрос в сезон удобнее закрыть звонком.',
                    'phone' => '+7 (913) 060-86-89',
                    'email' => null,
                    'whatsapp' => '79130608689',
                    'telegram' => 'motolevins',
                    'address' => 'Точка выдачи согласуется при бронировании (Геленджик, Анапа или Новороссийск — по договорённости).',
                    'working_hours' => "Ежедневно: 10:00–20:00 (МСК)\nВыдача и приём — по предварительной договорённости.",
                    'map_embed' => null,
                    'map_link' => null,
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ]
        );

        PageSection::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'page_id' => $page->id,
                'section_key' => 'contacts_tips',
            ],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Полезно знать',
                'section_type' => 'structured_text',
                'data_json' => [
                    'title' => 'Перед визитом',
                    'content' => '<ul><li>С собой — паспорт и водительское удостоверение категории&nbsp;A (оригиналы нужны при оформлении).</li><li>На первый визит заложите 15–20 минут: осмотр техники, акт приёма-передачи, короткий инструктаж.</li><li>В высокий сезон ответ в чате иногда задерживается — для срочных вопросов звонок обычно быстрее.</li></ul>',
                    'max_width' => 'prose',
                ],
                'sort_order' => 20,
                'status' => 'published',
                'is_visible' => true,
            ]
        );
    }

    private function seedMotolevinsRentalTermsPage(Tenant $tenant): void
    {
        $page = Page::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'usloviya-arenda',
            ],
            [
                'name' => 'Правила аренды',
                'template' => 'default',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        PageSection::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'page_id' => $page->id,
                'section_key' => 'main',
            ],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Вводный текст',
                'section_type' => 'rich_text',
                'data_json' => [
                    'content' => '<p>Ниже изложены условия аренды мотоцикла в структурированном виде. Формулировки соответствуют договору, который вы подписываете при выдаче техники. Нюансы по конкретной модели или датам всегда можно уточнить — <a href="/contacts">контакты для связи</a>.</p>',
                ],
                'sort_order' => 0,
                'status' => 'published',
                'is_visible' => true,
            ]
        );

        PageSection::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'page_id' => $page->id,
                'section_key' => 'terms_hero',
            ],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Баннер вводный',
                'section_type' => 'hero',
                'data_json' => [
                    'variant' => 'compact',
                    'heading' => 'Договор и правила в одном месте',
                    'subheading' => 'Общие положения, требования к арендатору, залог, страхование, бронирование, эксплуатация и возврат — по разделам ниже. Используйте содержание слева для быстрого перехода.',
                    'description' => '',
                    'video_poster' => '',
                    'video_src' => '',
                    'button_text' => '',
                    'button_url' => '',
                    'background_image' => '',
                    'overlay_dark' => true,
                    'chips' => [],
                ],
                'sort_order' => 5,
                'status' => 'published',
                'is_visible' => true,
            ]
        );

        PageSection::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('page_id', $page->id)
            ->whereIn('section_key', [
                'rule_age', 'rule_docs', 'rule_deposit', 'rule_insurance',
                'rule_booking', 'rule_use', 'rule_return', 'rule_fines',
            ])
            ->delete();

        $chapters = [
            [
                'key' => 'terms_general',
                'title' => 'Общие положения',
                'content' => '<p>Аренда осуществляется на условиях договора проката и акта приёма-передачи. Условия на сайте носят информационный характер; при расхождении приоритет имеет подписанный договор и акт. Арендатор обязан соблюдать ПДД РФ и правила безопасной эксплуатации мотоцикла.</p>',
            ],
            [
                'key' => 'terms_renter',
                'title' => 'Требования к арендатору',
                'content' => '<p>Возраст — не менее 21 года. Стаж управления мотоциклом по категории&nbsp;A — не менее двух лет. При выдаче проверяются паспорт и водительское удостоверение; права должны быть действительными на весь срок аренды.</p>',
            ],
            [
                'key' => 'terms_documents',
                'title' => 'Документы',
                'content' => '<p>Для оформления нужны оригиналы паспорта и водительского удостоверения с категорией&nbsp;A. Для предварительной брони могут запрашиваться копии или фото — оригиналы обязательны при подписании акта приёма-передачи.</p>',
            ],
            [
                'key' => 'terms_deposit_payment',
                'title' => 'Залог и оплата',
                'content' => '<p>Залог вносится для обеспечения исполнения обязательств и возвращается при сдаче техники без новых повреждений и нарушений условий договора, после осмотра представителем проката. Ориентиры по классу техники:</p><table><thead><tr><th>Класс техники</th><th>Ориентир залога</th></tr></thead><tbody><tr><td>Лёгкий туристический (до 400&nbsp;см³)</td><td>30&nbsp;000 – 45&nbsp;000&nbsp;₽</td></tr><tr><td>Средний / классический (400–900&nbsp;см³)</td><td>45&nbsp;000 – 60&nbsp;000&nbsp;₽</td></tr><tr><td>Крупный туринг или премиум (от 900&nbsp;см³)</td><td>60&nbsp;000 – 80&nbsp;000&nbsp;₽</td></tr></tbody></table><p>Итоговая сумма залога и порядок оплаты аренды фиксируются в договоре и могут зависеть от модели, сезона и длительности проката.</p>',
            ],
            [
                'key' => 'terms_insurance',
                'title' => 'Страхование и ответственность',
                'content' => '<p>В состав аренды входит полис ОСАГО в соответствии с договором. Расширенная защита (в том числе КАСКО без франшизы) может предлагаться как опция — уточняйте при бронировании. Ущерб, не покрытый страховкой, а также нарушения ПДД и условий договора возмещаются арендатором в порядке, указанном в договоре.</p>',
            ],
            [
                'key' => 'terms_operation',
                'title' => 'Правила эксплуатации',
                'content' => '<p>Мотоцикл передаётся в исправном состоянии; уровень топлива и пробег фиксируются в акте. Запрещено передавать управление третьим лицам, использовать технику в соревнованиях или на закрытых трассах без письменного согласия арендодателя, езда в состоянии алкогольного или наркотического опьянения. Соблюдайте регламент технического обслуживания и сигналы приборов.</p>',
            ],
            [
                'key' => 'terms_booking',
                'title' => 'Бронирование, перенос и отмена',
                'content' => '<p>Бронь считается подтверждённой после согласования дат, модели и условий с представителем проката. Перенос и отмена регулируются договором и фиксированной перепиской. Рекомендуем сохранять договорённости в письменном виде (мессенджер, электронная почта).</p>',
            ],
            [
                'key' => 'terms_return',
                'title' => 'Возврат техники',
                'content' => '<p>Возврат производится в согласованное время и место. При опоздании может начисляться дополнительная суточная ставка или неустойка по договору. В случае поломки или дорожно-транспортного происшествия немедленно свяжитесь с контактом, указанным в договоре.</p>',
            ],
            [
                'key' => 'terms_restrictions',
                'title' => 'Ограничения и запреты',
                'content' => '<p>Административные штрафы и проезд по платным участкам дорог оплачивает арендатор. Курение на технике, возврат в сильном загрязнении без согласованной мойки, утрата ключей или документов комплекта могут повлечь удержания из залога по тарифам арендодателя. Детальный перечень — в договоре и акте приёма-передачи.</p>',
            ],
            [
                'key' => 'terms_additional',
                'title' => 'Дополнительные условия',
                'content' => '<p>Арендодатель вправе отказать в выдаче техники при сомнениях в документах, состоянии арендатора или погодных/дорожных условиях, создающих повышенный риск. Спорные вопросы разрешаются переговорами; при невозможности согласования — в соответствии с законодательством РФ и договором.</p>',
            ],
        ];

        $order = 20;
        foreach ($chapters as $chapter) {
            PageSection::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'page_id' => $page->id,
                    'section_key' => $chapter['key'],
                ],
                [
                    'tenant_id' => $tenant->id,
                    'title' => $chapter['title'],
                    'section_type' => 'structured_text',
                    'data_json' => [
                        'title' => $chapter['title'],
                        'content' => $chapter['content'],
                        'max_width' => 'prose',
                    ],
                    'sort_order' => $order,
                    'status' => 'published',
                    'is_visible' => true,
                ]
            );
            $order += 10;
        }

        $allowedSectionKeys = array_merge(
            ['main', 'terms_hero'],
            array_column($chapters, 'key')
        );
        PageSection::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('page_id', $page->id)
            ->whereNotIn('section_key', $allowedSectionKeys)
            ->delete();
    }
}
