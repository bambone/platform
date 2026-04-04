<?php

use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\TenantSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Production-ready SEO copy for demo tenant Moto Levins (facts from публичного сайта и сидеров).
 * Без выдуманных адресов и рейтингов; цены и формулировки совпадают с витриной.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', 'motolevins')->value('id');
        if ($tenantId < 1) {
            return;
        }

        $domainRow = (string) DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('group', 'general')
            ->where('key', 'domain')
            ->value('value');
        $base = rtrim($domainRow, '/');
        $heroOg = $base !== '' ? $base.'/images/motolevins/marketing/hero-bg.png' : null;

        $llmsIntro = <<<'TXT'
Moto Levins — прокат мотоциклов Honda на побережье Чёрного моря: Геленджик, Анапа и Новороссийск. На сайте — каталог моделей с ценами за сутки, онлайн-бронирование и условия проката (возраст 21+, стаж от 2 лет по категории А, залог и страхование описаны в разделе условий). Экипировка включена; без скрытых платежей в объявленных тарифах.
TXT;

        $llmsEntries = [
            ['path' => '/', 'summary' => 'Главная: каталог на главной, маршруты, условия и CTA к бронированию.'],
            ['path' => '/motorcycles', 'summary' => 'Каталог моделей для аренды (заглушка-страница маршрута; основной каталог также на главной).'],
            ['path' => '/booking', 'summary' => 'Онлайн-бронирование: выбор дат и модели, проверка доступности.'],
            ['path' => '/contacts', 'summary' => 'Контакты: телефон, WhatsApp, Telegram и форма связи.'],
            ['path' => '/faq', 'summary' => 'Ответы на частые вопросы о документах, залоге, страховке и выезде в другие регионы.'],
            ['path' => '/about', 'summary' => 'Кратко о прокате и подходе к сервису.'],
            ['path' => '/prices', 'summary' => 'Раздел с тарифами и ценовой информацией.'],
            ['path' => '/usloviya-arenda', 'summary' => 'Полные условия аренды в конструкторе страниц.'],
            ['path' => '/reviews', 'summary' => 'Отзывы клиентов.'],
            ['path' => '/order', 'summary' => 'Заявка на аренду.'],
        ];

        TenantSetting::setForTenant($tenantId, 'seo.llms_intro', $llmsIntro, 'string');
        TenantSetting::setForTenant($tenantId, 'seo.llms_entries', json_encode($llmsEntries, JSON_UNESCAPED_UNICODE), 'string');

        $routeOverrides = [
            'faq' => [
                'title' => 'Частые вопросы об аренде мотоциклов — {site_name}',
                'description' => 'Документы, залог, страховка, лимит пробега и выезд в другие регионы — коротко перед бронированием у {site_name}.',
                'h1' => 'Частые вопросы об аренде',
            ],
            'about' => [
                'title' => 'О прокате {site_name} на Чёрном море',
                'description' => 'Прокат с 2024 года: понятные цены, экипировка включена, поддержка 24/7 и онлайн-бронирование мотоциклов Honda.',
                'h1' => 'О прокате Moto Levins',
            ],
            'motorcycles.index' => [
                'title' => 'Каталог мотоциклов Honda в аренду — {site_name}',
                'description' => 'Модели для города, побережья и дальних поездок. Сравните цены за сутки и перейдите к бронированию на сайте {site_name}.',
                'h1' => 'Каталог мотоциклов в аренду',
            ],
            'prices' => [
                'title' => 'Цены на аренду мотоциклов — {site_name}',
                'description' => 'Тарифы за сутки и длительность, что входит в стоимость на витрине и как уточнить итог при бронировании у {site_name}.',
                'h1' => 'Цены на мотоциклы',
            ],
            'order' => [
                'title' => 'Заявка на аренду мотоцикла — {site_name}',
                'description' => 'Оставьте контакты и даты — менеджер {site_name} подтвердит модель и условия проката.',
                'h1' => 'Заявка на аренду',
            ],
            'reviews' => [
                'title' => 'Отзывы о прокате {site_name}',
                'description' => 'Мнения клиентов о технике, выдаче и поездках по побережью с {site_name}.',
                'h1' => 'Отзывы клиентов',
            ],
            'articles.index' => [
                'title' => 'Статьи и материалы — {site_name}',
                'description' => 'Подборка материалов о поездках и аренде мотоциклов от команды {site_name}.',
                'h1' => 'Статьи',
            ],
            'booking.index' => [
                'title' => 'Бронирование мотоцикла онлайн — {site_name}',
                'description' => 'Выберите модель Honda, даты поездки и проверьте доступность перед оформлением заявки у {site_name}.',
                'h1' => 'Онлайн-бронирование',
            ],
            'booking.checkout' => [
                'title' => 'Оформление брони мотоцикла — {site_name}',
                'description' => 'Проверьте даты, модель и контакты перед отправкой заявки в {site_name}.',
                'h1' => 'Оформление бронирования',
            ],
            'booking.thank-you' => [
                'title' => 'Заявка на бронь принята — {site_name}',
                'description' => 'Мы получили заявку и свяжемся с вами для подтверждения деталей проката.',
                'h1' => 'Бронирование принято',
            ],
            'delivery.anapa' => [
                'title' => 'Доставка мотоциклов в Анапу — {site_name}',
                'description' => 'Информация о доставке техники в Анапу в рамках сервиса {site_name}.',
                'h1' => 'Доставка мотоциклов в Анапу',
            ],
            'delivery.gelendzhik' => [
                'title' => 'Доставка мотоциклов в Геленджик — {site_name}',
                'description' => 'Информация о доставке техники в Геленджик в рамках сервиса {site_name}.',
                'h1' => 'Доставка мотоциклов в Геленджик',
            ],
        ];

        TenantSetting::setForTenant($tenantId, 'seo.route_overrides', json_encode($routeOverrides, JSON_UNESCAPED_UNICODE), 'string');

        $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homePageId > 0) {
            SeoMeta::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'seoable_type' => Page::class,
                    'seoable_id' => $homePageId,
                ],
                [
                    'meta_title' => 'Аренда мотоциклов на Чёрном море — от 4 000 ₽/сутки | Moto Levins',
                    'meta_description' => 'Геленджик, Анапа и Новороссийск: премиальные Honda в прокат. Экипировка и страховка включены, без скрытых платежей в тарифах на сайте. Выберите модель и даты — онлайн-бронирование.',
                    'h1' => null,
                    'og_title' => 'Аренда мотоциклов на Чёрном море — Moto Levins',
                    'og_description' => 'От 4 000 ₽/сутки. Геленджик · Анапа · Новороссийск. Каталог Honda, бронирование и понятные условия.',
                    'og_image' => $heroOg,
                ],
            );
        }

        $contactsPageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'contacts')->value('id');
        if ($contactsPageId > 0) {
            SeoMeta::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'seoable_type' => Page::class,
                    'seoable_id' => $contactsPageId,
                ],
                [
                    'meta_title' => 'Контакты Moto Levins — телефон, WhatsApp и Telegram',
                    'meta_description' => 'Свяжитесь с прокатом Moto Levins: телефон +7 (913) 060-86-89, WhatsApp и Telegram @motolevins. Ответим по бронированию и условиям аренды.',
                    'h1' => 'Контакты',
                    'og_title' => 'Контакты — Moto Levins',
                    'og_description' => 'Телефон, WhatsApp, Telegram. Быстрый ответ по бронированию мотоциклов.',
                ],
            );
        }

        $termsPageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'usloviya-arenda')->value('id');
        if ($termsPageId > 0) {
            SeoMeta::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'seoable_type' => Page::class,
                    'seoable_id' => $termsPageId,
                ],
                [
                    'meta_title' => 'Условия аренды мотоциклов — Moto Levins',
                    'meta_description' => 'Возраст от 21 года, стаж от 2 лет по категории А, залог 30 000–80 000 ₽. ОСАГО на всех мотоциклах; КАСКО без франшизы — опция при бронировании. Документы и порядок выдачи.',
                    'h1' => 'Условия аренды',
                    'og_title' => 'Условия аренды — Moto Levins',
                    'og_description' => 'Правила проката, залог, страхование и требования к водителю — на одной странице.',
                ],
            );
        }

        $bikeSeoBySlug = [
            'honda-vfr-800f-1' => [
                'meta_title' => 'HONDA VFR 800F — аренда от 9 000 ₽/сутки | Moto Levins',
                'meta_description' => 'Туризм и трасса: баланс динамики и комфорта на длинной дистанции. Аренда Honda VFR 800F: характеристики и бронирование у Moto Levins.',
            ],
            'honda-cb-300r-2' => [
                'meta_title' => 'HONDA CB 300R — аренда от 4 000 ₽/сутки | Moto Levins',
                'meta_description' => 'Город и короткие выезды: лёгкое управление и отзывчивый характер. Аренда Honda CB 300R у Moto Levins.',
            ],
            'honda-nc-750-integra-3' => [
                'meta_title' => 'HONDA NC 750 INTEGRA — аренда от 7 500 ₽/сутки | Moto Levins',
                'meta_description' => 'Для ежедневных поездок и спокойного потока: DCT и комфортная посадка. Аренда Honda NC 750 Integra у Moto Levins.',
            ],
            'honda-stx-1300-4' => [
                'meta_title' => 'HONDA СТХ 1300 — аренда от 10 000 ₽/сутки | Moto Levins',
                'meta_description' => 'Путешествия вдвоём: расслабленная посадка и ровный ход на дальняк. Аренда Honda СТХ 1300 у Moto Levins.',
            ],
            'honda-nc-750s-5' => [
                'meta_title' => 'HONDA NC 750S — аренда от 5 000 ₽/сутки | Moto Levins',
                'meta_description' => 'Смешанный сценарий: трасса, загород и спокойная езда. Аренда Honda NC 750S у Moto Levins.',
            ],
            'honda-nc-750x-krasnyi-6' => [
                'meta_title' => 'HONDA NC 750X (красный) — аренда от 6 500 ₽/сутки | Moto Levins',
                'meta_description' => 'Побережье и лёгкий оффроуд: клиренс и уверенность на неровном покрытии. Аренда Honda NC 750X у Moto Levins.',
            ],
            'honda-nc-750x-belyi-7' => [
                'meta_title' => 'HONDA NC 750X (белый) — аренда от 6 500 ₽/сутки | Moto Levins',
                'meta_description' => 'Побережье и лёгкий оффроуд: клиренс и универсальность для поездок у моря. Аренда Honda NC 750X у Moto Levins.',
            ],
            'honda-ctx-700-8' => [
                'meta_title' => 'HONDA CTX 700 — аренда от 6 500 ₽/сутки | Moto Levins',
                'meta_description' => 'Путешествия вдвоём: низкая посадка и спокойный ход. Аренда Honda CTX 700 у Moto Levins.',
            ],
        ];

        foreach ($bikeSeoBySlug as $slug => $fields) {
            $mid = (int) DB::table('motorcycles')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($mid < 1) {
                continue;
            }
            SeoMeta::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'seoable_type' => Motorcycle::class,
                    'seoable_id' => $mid,
                ],
                array_merge($fields, [
                    'h1' => null,
                    'og_title' => null,
                    'og_description' => null,
                ]),
            );
        }
    }

    public function down(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', 'motolevins')->value('id');
        if ($tenantId < 1) {
            return;
        }

        foreach (['seo.llms_intro', 'seo.llms_entries', 'seo.route_overrides'] as $key) {
            $parts = explode('.', $key, 2);
            $group = $parts[0];
            $k = $parts[1] ?? $parts[0];
            DB::table('tenant_settings')->where('tenant_id', $tenantId)->where('group', $group)->where('key', $k)->delete();
            \Illuminate\Support\Facades\Cache::forget("tenant_settings.{$tenantId}.{$key}");
        }

        foreach (['home', 'contacts', 'usloviya-arenda'] as $slug) {
            $pid = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($pid > 0) {
                SeoMeta::query()->where('tenant_id', $tenantId)->where('seoable_type', Page::class)->where('seoable_id', $pid)->delete();
            }
        }

        $slugs = [
            'honda-vfr-800f-1', 'honda-cb-300r-2', 'honda-nc-750-integra-3', 'honda-stx-1300-4',
            'honda-nc-750s-5', 'honda-nc-750x-krasnyi-6', 'honda-nc-750x-belyi-7', 'honda-ctx-700-8',
        ];
        foreach ($slugs as $slug) {
            $mid = (int) DB::table('motorcycles')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($mid > 0) {
                SeoMeta::query()->where('tenant_id', $tenantId)->where('seoable_type', Motorcycle::class)->where('seoable_id', $mid)->delete();
            }
        }
    }
};
