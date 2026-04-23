<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Http\Controllers\HomeController;
use App\Models\AvailabilityRule;
use App\Models\BookableService;
use App\Models\Page;
use App\Models\SchedulingResource;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Scheduling\Enums\AssignmentStrategy;
use App\Scheduling\Enums\AvailabilityRuleType;
use App\Scheduling\Enums\CalendarUsageMode;
use App\Scheduling\Enums\SchedulingResourceType;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\Enums\TentativeEventsPolicy;
use App\Scheduling\Enums\UnconfirmedRequestsPolicy;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckMapsReviewCatalog;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use App\TenantSiteSetup\BookingNotificationsQuestionnaireRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Тенант Black Duck Detailing: тема black_duck, IA Q1, FAQ, демо-scheduling, квота.
 */
final class BlackDuckBootstrap extends Seeder
{
    /** URL-сегмент / идентификатор в БД; без дефиса, как поддомен. */
    public const SLUG = 'blackduck';

    /** Прод-поддомен: blackduck.rentbase.su (не black-duck.*). */
    public const PRIMARY_PUBLIC_HOST = 'blackduck.rentbase.su';

    private const PHONE = BlackDuckContentConstants::PHONE_DISPLAY;

    private const OFFICE_ADDRESS = BlackDuckContentConstants::ADDRESS_PUBLIC;

    public function run(): void
    {
        $existingId = (int) (DB::table('tenants')->where('theme_key', 'black_duck')->value('id')
            ?: DB::table('tenants')->whereIn('slug', [self::SLUG, 'black-duck'])->value('id'));
        if (! $existingId) {
            $this->createFullTenant();
        } else {
            $this->ensureContent($existingId);
        }
        self::synchronizeCanonicalDomain();
        $tid = (int) (DB::table('tenants')->where('theme_key', 'black_duck')->value('id')
            ?: DB::table('tenants')->whereIn('slug', [self::SLUG, 'black-duck'])->value('id'));
        if ($tid > 0) {
            HomeController::forgetCachedPayloadForTenant($tid);
        }
    }

    public static function rollback(): void
    {
        $tenantId = (int) (DB::table('tenants')->where('theme_key', 'black_duck')->value('id')
            ?: DB::table('tenants')->whereIn('slug', [self::SLUG, 'black-duck'])->value('id'));
        if ($tenantId <= 0) {
            return;
        }
        if (Schema::hasTable('reviews')) {
            DB::table('reviews')->where('tenant_id', $tenantId)->delete();
        }
        DB::table('page_sections')->where('tenant_id', $tenantId)->delete();
        DB::table('pages')->where('tenant_id', $tenantId)->delete();
        DB::table('form_configs')->where('tenant_id', $tenantId)->delete();
        DB::table('faqs')->where('tenant_id', $tenantId)->delete();
        DB::table('seo_meta')->where('tenant_id', $tenantId)->delete();
        DB::table('tenant_settings')->where('tenant_id', $tenantId)->delete();
        DB::table('tenant_domains')->where('tenant_id', $tenantId)->delete();
        DB::table('tenants')->where('id', $tenantId)->delete();
    }

    private function createFullTenant(): void
    {
        $planId = (int) (DB::table('plans')->value('id') ?? 0);
        $ownerId = (int) (DB::table('users')->value('id') ?? 0);

        $now = now();
        $row = [
            'name' => 'Black Duck Detailing',
            'slug' => self::SLUG,
            'brand_name' => 'Black Duck Detailing',
            'legal_name' => 'Black Duck Detailing',
            'theme_key' => 'black_duck',
            'status' => 'active',
            'timezone' => 'Asia/Yekaterinburg',
            'locale' => 'ru',
            'currency' => 'RUB',
            'country' => 'RU',
            'plan_id' => $planId > 0 ? $planId : null,
            'owner_user_id' => $ownerId > 0 ? $ownerId : null,
            'scheduling_module_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $canonical = BlackDuckContentConstants::CANONICAL_TENANT_ID;
        if (! DB::table('tenants')->where('id', $canonical)->exists()) {
            DB::table('tenants')->insert(array_merge(['id' => $canonical], $row));
            $tenantId = $canonical;
            if (DB::getDriverName() === 'mysql') {
                $max = (int) (DB::table('tenants')->max('id') ?? 0);
                $next = max($canonical + 1, $max + 1);
                DB::statement('ALTER TABLE tenants AUTO_INCREMENT = '.$next);
            }
        } else {
            $tenantId = (int) DB::table('tenants')->insertGetId($row);
        }

        $this->insertDomain($tenantId, $now);
        $this->applyPublicSettings($tenantId);
        $this->insertAllPages($tenantId, $now);
        $this->seedFaqs($tenantId, $now);
        $this->seedReviews($tenantId, $now);
        $this->seedBlackDuckMapsReviews($tenantId, $now);
        $this->ensureFormConfig($tenantId, $now);
        $this->applySeoForPages($tenantId, $now);
        $this->ensureBookingQuestionnaireDefaults($tenantId);
        $this->ensureDemoScheduling($tenantId);
        $this->ensureQuota($tenantId);
        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    private function ensureContent(int $tenantId): void
    {
        $now = now();
        $this->insertDomain($tenantId, $now);
        $this->applyPublicSettings($tenantId);
        $this->insertAllPages($tenantId, $now);
        if ($this->faqsEmpty($tenantId)) {
            $this->seedFaqs($tenantId, $now);
        }
        if (Schema::hasTable('reviews') && (int) DB::table('reviews')->where('tenant_id', $tenantId)->count() < 1) {
            $this->seedReviews($tenantId, $now);
        }
        $this->seedBlackDuckMapsReviews($tenantId, $now);
        $this->ensureFormConfig($tenantId, $now);
        $this->applySeoForPages($tenantId, $now);
        $this->ensureBookingQuestionnaireDefaults($tenantId);
        $this->ensureDemoScheduling($tenantId);
        $this->ensureQuota($tenantId);
        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    private function faqsEmpty(int $tenantId): bool
    {
        return (int) DB::table('faqs')->where('tenant_id', $tenantId)->count() < 1;
    }

    private function applyPublicSettings(int $tenantId): void
    {
        TenantSetting::setForTenant($tenantId, 'contacts.phone', self::PHONE, 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.email', BlackDuckContentConstants::EMAIL, 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.public_office_address', self::OFFICE_ADDRESS, 'string');
        TenantSetting::setForTenant($tenantId, 'general.primary_city', 'Челябинск', 'string');
    }

    private function ensureBookingQuestionnaireDefaults(int $tenantId): void
    {
        $key = BookingNotificationsQuestionnaireRepository::SETTING_KEY;
        $existing = TenantSetting::getForTenant($tenantId, $key, null);
        if (is_string($existing) && trim($existing) !== '' && $existing !== '[]') {
            return;
        }
        $repo = app(BookingNotificationsQuestionnaireRepository::class);
        $draft = $repo->defaults();
        $draft['meta_brand_name'] = 'Black Duck Detailing';
        $draft['meta_timezone'] = 'Asia/Yekaterinburg';
        $draft['dest_email'] = BlackDuckContentConstants::EMAIL;
        $draft['sched_requires_confirmation'] = true;
        TenantSetting::setForTenant(
            $tenantId,
            $key,
            json_encode($draft, JSON_UNESCAPED_UNICODE),
            'string',
        );
    }

    private function ensureQuota(int $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }
        app(TenantStorageQuotaService::class)->ensureQuotaRecord($tenant);
    }

    private function insertDomain(int $tenantId, $now): void
    {
        $host = self::PRIMARY_PUBLIC_HOST;
        if (DB::table('tenant_domains')->where('tenant_id', $tenantId)->where('host', $host)->exists()) {
            return;
        }

        DB::table('tenant_domains')->insert([
            'tenant_id' => $tenantId,
            'host' => $host,
            'type' => 'subdomain',
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => 'not_required',
            'verified_at' => $now,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Снимаем слепки вроде black-duck.* / black-duck., поднимаем slug, канон: {@see PRIMARY_PUBLIC_HOST} как primary.
     * Вызывается из {@see run()} после сидов; tenant_domains с прода/restore в whitelist нет — без этого остаётся мусор.
     */
    public static function synchronizeCanonicalDomain(): void
    {
        $orphanIds = DB::table('tenant_domains as td')
            ->leftJoin('tenants as t', 't.id', '=', 'td.tenant_id')
            ->whereNull('t.id')
            ->pluck('td.id');
        if ($orphanIds->isNotEmpty()) {
            DB::table('tenant_domains')->whereIn('id', $orphanIds->all())->delete();
        }

        $target = self::PRIMARY_PUBLIC_HOST;
        $tid = (int) (DB::table('tenants')->where('theme_key', 'black_duck')->value('id')
            ?: DB::table('tenants')->whereIn('slug', [self::SLUG, 'black-duck'])->value('id'));
        if ($tid <= 0) {
            return;
        }

        DB::table('tenants')
            ->where('id', $tid)
            ->where('slug', 'black-duck')
            ->update(['slug' => self::SLUG, 'updated_at' => now()]);

        DB::table('tenant_domains')
            ->where('tenant_id', $tid)
            ->where('host', 'like', 'black-duck%')
            ->delete();

        $now = now();
        if (! DB::table('tenant_domains')->where('tenant_id', $tid)->where('host', $target)->exists()) {
            DB::table('tenant_domains')->insert([
                'tenant_id' => $tid,
                'host' => $target,
                'type' => 'subdomain',
                'is_primary' => false,
                'status' => 'active',
                'ssl_status' => 'not_required',
                'verified_at' => $now,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('tenant_domains')->where('tenant_id', $tid)->where('host', $target)->exists()) {
            return;
        }

        DB::table('tenant_domains')->where('tenant_id', $tid)->update(['is_primary' => false]);
        DB::table('tenant_domains')
            ->where('tenant_id', $tid)
            ->where('host', $target)
            ->update(['is_primary' => true, 'updated_at' => $now]);
    }

    /**
     * Идемпотентно: страницы и секции — только отсутствующие.
     */
    private function insertAllPages(int $tenantId, $now): void
    {
        $definitions = $this->pageDefinitions();
        foreach ($definitions as $def) {
            $pageId = $this->ensurePageRow($tenantId, $def, $now);
            if ($pageId > 0 && isset($def['sections'])) {
                $this->ensureSections($pageId, $tenantId, $def['sections'], $now);
            }
        }
    }

    /**
     * @return list<array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections?: list<array<string, mixed>>}>
     */
    private function pageDefinitions(): array
    {
        return [
            $this->defHome(),
            $this->defServiceHub(),
            $this->defServiceLanding('detejling-mojka', 'Детейлинг-мойка', 'Короткая детерминированная услуга: запись онлайн после настройки расписания.'),
            $this->defServiceLanding('setki-radiatora', 'Установка защитных сеток', 'Сетки на радиатор: подбор по геометрии, монтаж под модель; согласуем сроки.'),
            $this->defServiceLanding('antidozhd', 'Антидождь', 'Гидрофоб и обзор: составы, слои и срок согласуем письменно при записи.'),
            $this->defServiceLanding('remont-skolov', 'Ремонт сколов', 'Точка входа: сколы и царапины LKP — план и глубина работ после осмотра.'),
            $this->defServiceLanding('shumka', 'Шумоизоляция', 'Объём работ и длительность — после диагностики на месте.'),
            $this->defServiceLanding('kozha-keramika', 'Кожа: керамика салона', 'По тесту материалов; серия согласуется, без «слепой» площади.'),
            $this->defServiceLanding('tonirovka', 'Тонировка / оптика', 'Согласование плёнки и вариантов; оптика — по регламенту ГИБДД при необходимости.'),
            $this->defServiceLanding('keramika', 'Керамическое покрытие', 'Серия этапов; подтверждение слотов менеджером.'),
            $this->defServiceLanding('restavratsiya-kozhi', 'Реставрация кожи', 'Сезоны износа, пигментация, шов — план и фиксация цвета в ТЗ.'),
            $this->defServiceLanding('himchistka-diskov', 'Химчистка дисков', 'Добор до внутриспицев и суппорт-зон — без рисков на ЛКП диска.'),
            $this->defServiceLanding('bronirovanie-salona', 'Бронирование салона', 'Плёнки на пластик, дисплеи, пороги; приоритизация зон вместе с вами.'),
            $this->defServiceLanding('himchistka-kuzova', 'Химчистка кузова', 'Деинкрустация, подготовка под LKP и следующий этап (полировка/керамика/PPF).'),
            $this->defServiceLanding('ppf', 'Антигравийная плёнка (PPF)', 'Покрытия зон и плёнка — по осмотру и макету.'),
            $this->defServiceLanding('podkapotnaya-himchistka', 'Подкапотное: чистка и консервация', 'Сухо/мокро по вводу, консервация пластиков и снятие/маркировка кожухов по чек-листу.'),
            $this->defServiceLanding('polirovka-kuzova', 'Полировка кузова', 'Абразив и финиш — по состоянию ЛКП; длительность планируется по осмотру.'),
            $this->defServiceLanding('himchistka-salona', 'Химчистка салона', 'Интерьер: сроки и объём согласовываются после осмотра.'),
            $this->defServiceLanding('pdr', 'PDR (безпокрасочный ремонт вмятин)', 'Оценка доступа к вмятине и сроки — на осмотре.'),
            $this->defServiceLanding('predprodazhnaya', 'Предпродажная подготовка', 'Комплекс работ под продажу авто: срок и состав — по согласованию.'),
            $this->defCases(),
            $this->defReviewsPage(),
            $this->defFaqPage(),
            $this->defContacts(),
            $this->defPromo(),
            $this->defPrivacy(),
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defHome(): array
    {
        return [
            'slug' => 'home',
            'name' => 'Главная',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
            'sections' => $this->homeSectionRows(),
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defServiceHub(): array
    {
        return [
            'slug' => 'uslugi',
            'name' => 'Услуги',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 10,
            'sections' => [
                $this->sec('intro', 'rich_text', 'О хабе', 0, [
                    'content' => '<p class="lead">Выберите направление: короткие работы с онлайн-записью, сложные — по заявке и согласованию смены/осмотра.</p>',
                ]),
                $this->sec('service_hub', 'service_hub_grid', 'Карта услуг', 10, [
                    'heading' => 'Ключевые направления',
                    'items' => [
                        ['title' => 'Детейлинг-мойка', 'price_from' => 'от —', 'duration' => '1–2 ч', 'online_booking' => true, 'needs_confirmation' => false, 'cta_url' => '/detejling-mojka'],
                        ['title' => 'Химчистка', 'price_from' => 'от —', 'duration' => 'по оценке', 'online_booking' => false, 'needs_confirmation' => true, 'cta_url' => '/himchistka-salona'],
                        ['title' => 'Полировка', 'price_from' => 'по ЛКП', 'duration' => '1–2 дня', 'online_booking' => false, 'needs_confirmation' => true, 'cta_url' => '/polirovka-kuzova'],
                        ['title' => 'Керамика', 'price_from' => 'по пакету', 'duration' => 'по плану', 'online_booking' => false, 'needs_confirmation' => true, 'cta_url' => '/keramika'],
                        ['title' => 'PPF', 'price_from' => 'по осмотру', 'duration' => 'по плану', 'online_booking' => false, 'needs_confirmation' => true, 'cta_url' => '/ppf'],
                        ['title' => 'Тонировка', 'price_from' => 'от —', 'duration' => 'по смене', 'online_booking' => false, 'needs_confirmation' => true, 'cta_url' => '/tonirovka'],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defServiceLanding(string $slug, string $name, string $lead): array
    {
        $sections = [
            $this->sec('hero', 'hero', 'Hero', 0, [
                'variant' => 'full_background',
                'heading' => $name,
                'subheading' => $lead,
                'button_text' => 'Состав и этапы',
                'button_url' => '#bd-service-included',
                'secondary_button_text' => 'Записаться',
                'secondary_button_url' => BlackDuckContentConstants::serviceLandingBookIntentUrl($slug),
                'overlay_dark' => true,
            ]),
            $this->sec('body_intro', 'rich_text', 'О услуге', 8, [
                'content' => '<p class="text-pretty leading-relaxed">'.e($lead).'</p>',
            ]),
            $this->sec('service_included', 'list_block', 'Что входит', 12, [
                'title' => 'Что входит',
                'variant' => 'bullets',
                'items' => [
                    ['title' => 'Согласование', 'text' => 'Объём и срок после осмотра или заявки.'],
                ],
            ]),
            $this->sec('body', 'rich_text', 'Суть', 18, [
                'content' => '',
            ], false),
            $this->sec('service_faq', 'faq', 'FAQ', 25, [
                'section_heading' => 'Вопросы по услуге',
                'source' => 'faqs_table_service',
                'faq_category' => $slug,
                'items' => [],
            ]),
            $this->sec('service_review_feed', 'review_feed', 'Отзывы', 27, [
                'heading' => 'Отзывы клиентов',
                'subheading' => 'Выдержки с 2ГИС и Яндекс Карт по этой услуге.',
                'layout' => 'service_maps_compact',
                'limit' => BlackDuckMapsReviewCatalog::REVIEWS_PER_LANDING,
                'category_key' => $slug,
                'section_id' => 'bd-service-reviews',
                'maps_link_2gis' => BlackDuckContentConstants::URL_2GIS_REVIEWS_TAB,
                'maps_link_yandex' => BlackDuckContentConstants::URL_YANDEX_MAPS_REVIEWS_TAB,
                'show_maps_cta' => true,
            ]),
        ];
        if (in_array($slug, BlackDuckMediaCatalog::SERVICE_PROOF_LANDING_SLUGS, true)) {
            $sections[] = $this->sec('service_proof', 'case_study_cards', 'На фото', 40, [
                'heading' => 'На фото',
                'items' => [],
            ]);
        }
        $inquiry = BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug);
        $sections[] = $this->sec('service_final_cta', 'rich_text', 'Заявка', 50, [
            'content' => '<p class="text-zinc-300">Нужен расчёт или запись? <a class="font-medium text-[#36C7FF] underline" href="'.e($inquiry).'">Оставьте заявку</a> — в форме уже будет выбрана услуга «'.e($name).'».</p>',
        ]);

        return [
            'slug' => $slug,
            'name' => $name,
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
            'sections' => $sections,
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defCases(): array
    {
        return [
            'slug' => 'raboty',
            'name' => 'Работы / кейсы',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 20,
            'sections' => [
                $this->sec('works_hero', 'hero', 'Видео', 0, [
                    'variant' => 'full_background',
                    'heading' => 'Работы Black Duck',
                    'subheading' => 'Фрагменты этапов и итогов. Полный подбор — в зале и по заявке.',
                    'button_text' => 'Заявка и расчёт',
                    'button_url' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                    'video_src' => '',
                    'video_poster' => '',
                    'overlay_dark' => true,
                ]),
                $this->sec('works_portfolio', 'works_portfolio', 'Портфолио', 5, [
                    'heading' => 'Портфолио',
                    'intro' => '',
                    'filters' => [],
                    'gallery_items' => [],
                    'primary_cta_label' => 'Заявка и расчёт',
                    'primary_cta_href' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                ]),
                $this->sec('works_before_after', 'before_after_slider', 'До / после', 10, [
                    'heading' => 'До и после',
                    'pairs' => [],
                ]),
                $this->sec('case_list', 'case_study_cards', 'Кейсы', 20, [
                    'heading' => 'Примеры работ',
                    'items' => [
                        [
                            'vehicle' => 'SUV, тёмный кузов',
                            'task' => 'Керамика + подготовка',
                            'result' => 'Глянец и гидрофоб до согласованного уровня.',
                            'duration' => '2–3 дня',
                        ],
                        [
                            'vehicle' => 'Седан',
                            'task' => 'PPF переднего сегмента',
                            'result' => 'Снижение рисков сколов в зоне ПТЗ.',
                            'duration' => 'по плану',
                        ],
                    ],
                ]),
                $this->sec('works_cta', 'rich_text', 'Связь', 40, [
                    'content' => '<p class="text-zinc-300">Готовы обсудить работу? <a class="font-medium text-[#36C7FF] underline" href="'.e(BlackDuckContentConstants::PRIMARY_LEAD_URL).'">Оставьте заявку</a> — ответим и согласуем план.</p>',
                ]),
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defReviewsPage(): array
    {
        return [
            'slug' => 'otzyvy',
            'name' => 'Отзывы',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 30,
            'sections' => [
                $this->sec('review_feed', 'review_feed', 'Отзывы', 0, [
                    'heading' => 'Отзывы клиентов',
                    'subheading' => 'Собраны с публичных источников и с сайта.',
                    'layout' => 'grid',
                    'limit' => 24,
                ]),
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defFaqPage(): array
    {
        return [
            'slug' => 'faq',
            'name' => 'FAQ',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 40,
            'sections' => [
                $this->sec('faqs', 'content_faq', 'Вопросы', 0, [
                    'title' => 'Вопросы о записи и сроках',
                    'items' => [
                        [
                            'question' => 'Можно ли зафиксировать бокс на «сегодня»?',
                            'answer' => '<p>Короткие услуги — по свободным слотам в онлайн-записи. Сложные работы согласуются с календарём смены.</p>',
                        ],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defContacts(): array
    {
        return [
            'slug' => 'contacts',
            'name' => 'Контакты',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 50,
            'sections' => [
                $this->sec('contacts', 'contacts', 'Контакты', 0, [
                    'heading' => 'Контакты',
                    'phone' => self::PHONE,
                    'email' => BlackDuckContentConstants::EMAIL,
                    'address' => self::OFFICE_ADDRESS,
                    'map_enabled' => false,
                ]),
                $this->sec('contact_inquiry', 'contact_inquiry', 'Сообщение', 20, [
                    'enabled' => true,
                    'heading' => 'Написать',
                    'section_id' => 'contact-inquiry',
                    'submit_label' => 'Отправить',
                    'success_message' => 'Сообщение получено. Ответим в рабочее время.',
                ]),
                $this->sec('contact_faq', 'content_faq', 'Короткий FAQ', 40, [
                    'title' => 'Частые вопросы',
                    'items' => [
                        [
                            'question' => 'Как добраться до детейлинга?',
                            'answer' => '<p>'.e(self::OFFICE_ADDRESS).'</p>',
                        ],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defPromo(): array
    {
        return [
            'slug' => 'akcii',
            'name' => 'Акции и сертификаты',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 45,
            'sections' => [
                $this->sec('stub', 'rich_text', 'Акции', 0, [
                    'content' => '<p>Актуальные предложения и подарочные сертификаты публикуются по мере согласования. Оставьте заявку — менеджер пришлёт варианты.</p>',
                ]),
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int, sections: list<array<string, mixed>>}
     */
    private function defPrivacy(): array
    {
        return [
            'slug' => 'privacy-policy',
            'name' => 'Политика конфиденциальности',
            'template' => 'default',
            'status' => 'published',
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
            'sections' => [
                $this->sec('legal', 'rich_text', 'Текст', 0, [
                    'content' => '<p>Настоящий текст — заглушка Q1. Замените в конструкторе на утверждённую редакцию с учётом 152-ФЗ и условий сбора заявок.</p>',
                ]),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function homeSectionRows(): array
    {
        $preview = BlackDuckContentConstants::serviceMatrixHomePreview();
        $subs = BlackDuckContentConstants::homeServiceCardPreviewSubtitlesBySlug();
        $hubItems = [];
        foreach ($preview as $row) {
            $slug = (string) $row['slug'];
            $cta = str_starts_with($slug, '#') ? BlackDuckContentConstants::PRIMARY_LEAD_URL : '/'.$slug;
            $hubItems[] = [
                'title' => $row['title'],
                'card_subtitle' => (string) ($subs[$slug] ?? $row['blurb']),
                'price_from' => 'по задаче',
                'duration' => 'по плану',
                'online_booking' => $slug === 'detejling-mojka',
                'needs_confirmation' => $slug !== 'detejling-mojka',
                'booking_mode' => (string) $row['booking_mode'],
                'cta_url' => $cta,
                'image_url' => '',
            ];
        }

        return [
            $this->sec('expert_hero', 'expert_hero', 'Hero', 0, [
                'heading' => 'Black Duck Detailing',
                'subheading' => 'Премиальный детейлинг в Челябинске: защита ЛКП, химчистка, PPF, тонировка, запись и расчёт онлайн.',
                'description' => '',
                'primary_cta_label' => 'Записаться',
                'primary_cta_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                'secondary_cta_label' => 'Получить расчёт',
                'secondary_cta_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                'trust_badges' => [
                    ['text' => 'Работаем по предварительной записи'],
                    ['text' => 'Онлайн-заявка и согласование сложных работ'],
                    ['text' => 'Короткие услуги — онлайн-слоты после настройки'],
                ],
            ]),
            $this->sec('availability_ribbon', 'availability_ribbon', 'Инфо', 5, [
                'text' => 'Сложные услуги подтверждаются менеджером; быстрые мойки — в онлайн-расписании после настройки слотов. Лента информирует и не заменяет фактическую загрузку боксов.',
            ]),
            $this->sec('service_hub', 'service_hub_grid', 'Направления', 10, [
                'heading' => 'Ключевые направления',
                'items' => $hubItems,
            ]),
            $this->sec('before_after', 'before_after_slider', 'До/после', 20, [
                'heading' => 'Результат в деталях',
                'proof_works_cta_label' => 'Смотреть работы',
                'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                'pairs' => [],
            ]),
            $this->sec('case_cards', 'case_study_cards', 'Кейсы', 30, [
                'heading' => 'Свежие проекты',
                'proof_works_cta_label' => 'Смотреть работы',
                'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                'items' => [
                    [
                        'vehicle' => 'SUV',
                        'task' => 'Защитный пакет (PPF + керамика зоны риска)',
                        'result' => 'Согласованный глянец, уход за стыками плёнки.',
                        'duration' => '3 дня',
                    ],
                ],
            ]),
            $this->sec('reviews', 'review_feed', 'Отзывы', 50, [
                'heading' => 'Отзывы',
                'subheading' => 'Что говорят владельцы авто',
                'layout' => 'grid',
                'limit' => 6,
                'category_key' => 'service',
            ]),
            $this->sec('faq', 'faq', 'FAQ', 60, [
                'section_heading' => 'Частые вопросы',
                'source' => 'faqs_table',
            ]),
            // messenger_capture_bar не на главной: телефон и мессенджеры уже в футере — без дубля и «пустого» острова перед футером.
            $this->sec('sticky_cta', 'sticky_mobile_cta_dock', 'Моб. CTA', 88, [
                'enabled' => true,
                'label_call' => 'Позвонить',
                'label_messenger' => 'Написать',
                'label_book' => 'Запись',
                'label_quote' => 'Расчёт',
                'book_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                'quote_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sec(string $key, string $type, string $title, int $order, array $data, bool $isVisible = true): array
    {
        return [
            'section_key' => $key,
            'section_type' => $type,
            'title' => $title,
            'sort_order' => $order,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'is_visible' => $isVisible,
        ];
    }

    /**
     * @param  array{slug: string, name: string, template: string, status: string, show_in_main_menu: bool, main_menu_sort_order: int}  $def
     */
    private function ensurePageRow(int $tenantId, array $def, $now): int
    {
        $existing = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', $def['slug'])
            ->value('id');
        if ($existing > 0) {
            return $existing;
        }

        return (int) DB::table('pages')->insertGetId([
            'tenant_id' => $tenantId,
            'slug' => $def['slug'],
            'name' => $def['name'],
            'template' => $def['template'],
            'status' => $def['status'],
            'published_at' => $now,
            'show_in_main_menu' => $def['show_in_main_menu'],
            'main_menu_sort_order' => $def['main_menu_sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function ensureSections(int $pageId, int $tenantId, array $sections, $now): void
    {
        foreach ($sections as $s) {
            $key = (string) $s['section_key'];
            $exists = DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $pageId)
                ->where('section_key', $key)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('page_sections')->insert([
                'page_id' => $pageId,
                'tenant_id' => $tenantId,
                'section_key' => $s['section_key'],
                'section_type' => $s['section_type'],
                'title' => $s['title'] ?? null,
                'sort_order' => (int) ($s['sort_order'] ?? 0),
                'data_json' => is_string($s['data_json'] ?? null) ? $s['data_json'] : json_encode($s['data_json'] ?? [], JSON_UNESCAPED_UNICODE),
                'is_visible' => array_key_exists('is_visible', $s) ? (bool) $s['is_visible'] : true,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedFaqs(int $tenantId, $now): void
    {
        if (! $this->faqsEmpty($tenantId)) {
            return;
        }
        $pairs = [
            ['Сколько длится мойка премиум-класса?', 'Ориентир 1,5–2,5 часа в зависимости от класса кузова и степени загрязнения. Точное время скажет мастер по чек-листу.'],
            ['Почему «длинные» работы нельзя забронировать как мгновенный слот?', 'Нужен осмотр, план смен, иногда согласование поставки плёнки. Мы оставляем заявку и подтверждаем окно.'],
            ['Как устроена онлайн-запись на короткие услуги?', 'После настройки расписания и бокса доступны реальные слоты. Контентные подсказки на сайте не гарантируют свободный бокс сильнее движка слотов.'],
            ['Нужен ли оставлять авто на ночь?', 'Для ряда работ — да, заранее согласуем приём, ключи, видео-отчёт и хранение.'],
            ['Можно ли получить цену «сразу в чате»?', 'Да для типовых пакетов; для сложных — после осмотра или по фото, если так принято.'],
        ];
        $sort = 0;
        foreach ($pairs as [$q, $a]) {
            DB::table('faqs')->insert([
                'tenant_id' => $tenantId,
                'question' => $q,
                'answer' => $a,
                'category' => 'general',
                'sort_order' => ($sort += 10),
                'status' => 'published',
                'show_on_home' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedReviews(int $tenantId, $now): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }
        if ((int) DB::table('reviews')->where('tenant_id', $tenantId)->count() > 0) {
            return;
        }
        $rows = [
            [
                'name' => 'Анна',
                'headline' => 'Керамика + подготовка',
                'text_short' => 'Собрали ЛКП бережно, верх ведёт себя предсказуемо в дождь.',
                'text_long' => 'Собрали ЛКП бережно, верх ведёт себя предсказуемо в дождь, стекло с гидрофобом — отдельный кайф.',
            ],
            [
                'name' => 'Илья',
                'headline' => 'PPF зоны риска',
                'text_short' => 'Плёнка ровно, кромки спрятаны, без «пузырей» на фарах.',
                'text_long' => 'Сделали передний сегмент, кромки аккуратно, без «оранжевого кромки». Смотрю на сколы и спокоен в ПТЗ.',
            ],
            [
                'name' => 'Команда',
                'headline' => 'Сложная химчистка',
                'text_short' => 'Салон как новый, запаха химии не осталось.',
                'text_long' => 'Тёмный салон, светлая кожа — вынесли пятна без пересушки, запаха химии нет, вернули мягкость рулю.',
            ],
        ];
        foreach ($rows as $r) {
            DB::table('reviews')->insert(array_merge($r, [
                'tenant_id' => $tenantId,
                'text' => '',
                'category_key' => 'service',
                'city' => 'Челябинск',
                'rating' => 5,
                'media_type' => 'text',
                'is_featured' => true,
                'sort_order' => 10,
                'date' => $now->toDateString(),
                'source' => 'site',
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function seedBlackDuckMapsReviews(int $tenantId, $now): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }
        if ((int) DB::table('reviews')->where('tenant_id', $tenantId)->where('source', BlackDuckMapsReviewCatalog::SOURCE)->count() > 0) {
            return;
        }
        $batch = BlackDuckMapsReviewCatalog::rowsForDatabaseSeed();
        if ($batch === []) {
            return;
        }
        foreach ($batch as $row) {
            $meta = $row['meta_json'] ?? [];
            unset($row['meta_json']);
            DB::table('reviews')->insert(array_merge($row, [
                'tenant_id' => $tenantId,
                'meta_json' => json_encode(is_array($meta) ? $meta : [], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function ensureFormConfig(int $tenantId, $now): void
    {
        $fields = [
            'goal_text' => ['label' => 'Задача: запись, расчёт, осмотр', 'required' => true],
            'preferred_schedule' => ['label' => 'Удобное время звонка', 'required' => false],
            'comment' => ['label' => 'Авто, кузов, пожелания', 'required' => false],
        ];
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $existingId = (int) DB::table('form_configs')
            ->where('tenant_id', $tenantId)
            ->where('form_key', 'expert_lead')
            ->value('id');
        if ($existingId <= 0) {
            DB::table('form_configs')->insert([
                'tenant_id' => $tenantId,
                'form_key' => 'expert_lead',
                'title' => 'Black Duck: заявка',
                'description' => 'Запись и расчёт по детейлингу',
                'is_enabled' => true,
                'recipient_email' => BlackDuckContentConstants::EMAIL,
                'success_message' => 'Спасибо! Мы свяжемся для подтверждения сроков и бокса.',
                'error_message' => 'Не удалось отправить. Позвоните или напишите в мессенджер.',
                'fields_json' => $fieldsJson,
                'settings_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }
        DB::table('form_configs')->where('id', $existingId)->update([
            'title' => 'Black Duck: заявка',
            'fields_json' => $fieldsJson,
            'recipient_email' => BlackDuckContentConstants::EMAIL,
            'updated_at' => $now,
        ]);
    }

    private function applySeoForPages(int $tenantId, $now): void
    {
        $homeId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homeId < 1) {
            return;
        }
        $jsonLd = $this->localBusinessJsonLd();
        $payload = [
            'meta_title' => 'Black Duck Detailing — детейлинг в Челябинске',
            'meta_description' => 'Мойка, керамика, PPF, тонировка, химчистка. Заявка на запись и расчёт, согласование сложных работ.',
            'h1' => 'Black Duck Detailing',
            'og_title' => 'Black Duck Detailing',
            'og_description' => 'Премиальный детейлинг, онлайн-заявка и согласование длительных работ.',
            'is_indexable' => true,
            'is_followable' => true,
            'json_ld' => $jsonLd,
        ];
        SeoMeta::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'seoable_type' => Page::class,
                'seoable_id' => $homeId,
            ],
            $payload,
        );
        $other = [
            'uslugi' => ['Услуги — Black Duck Detailing', 'Карта направлений: мойка, защита ЛКП, шумоизоляция, тонировка и другое.'],
            'contacts' => ['Контакты — Black Duck Detailing', 'Телефон, email и адрес приёма. Запросите точку въезда у менеджера.'],
            'faq' => ['FAQ — Black Duck Detailing', 'Запись, сроки, ночь в сервисе, цена «от» и осмотр.'],
        ];
        foreach ($other as $slug => [$title, $desc]) {
            $pid = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($pid < 1) {
                continue;
            }
            SeoMeta::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'seoable_type' => Page::class,
                    'seoable_id' => $pid,
                ],
                [
                    'meta_title' => $title,
                    'meta_description' => $desc,
                    'h1' => null,
                    'is_indexable' => true,
                    'is_followable' => true,
                    'json_ld' => null,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function localBusinessJsonLd(): array
    {
        $org = [
            '@type' => 'AutoRepair',
            '@id' => url('/').'#org',
            'name' => 'Black Duck Detailing',
            'telephone' => self::PHONE,
            'url' => url('/'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'ул. Артиллерийская, 117/10',
                'addressLocality' => BlackDuckContentConstants::ADDRESS_CITY,
                'addressCountry' => 'RU',
            ],
        ];
        $wash = [
            '@type' => 'Service',
            'name' => 'Детейлинг-мойка',
            'serviceType' => 'Car wash and exterior detailing',
            'provider' => ['@id' => url('/').'#org'],
            'areaServed' => [
                '@type' => 'City',
                'name' => 'Челябинск',
            ],
        ];

        return [$org, $wash];
    }

    private function ensureDemoScheduling(int $tenantId): void
    {
        if (! Schema::hasTable('bookable_services')) {
            return;
        }
        $slug = (string) DB::table('tenants')->where('id', $tenantId)->value('slug');
        if ($slug !== self::SLUG) {
            return;
        }
        if (BookableService::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }
        $tz = 'Asia/Yekaterinburg';
        $res = SchedulingResource::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenantId,
            'resource_type' => SchedulingResourceType::Room,
            'label' => 'Мойка / подготовка A',
            'timezone' => $tz,
            'tentative_events_policy' => TentativeEventsPolicy::TreatAsBusy,
            'unconfirmed_requests_policy' => UnconfirmedRequestsPolicy::ConfirmedOnly,
            'is_active' => true,
        ]);
        $wash = BookableService::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenantId,
            'slug' => 'detailing-wash',
            'title' => 'Детейлинг-мойка (онлайн)',
            'description' => 'Короткая услуга: реальные слоты через расписание.',
            'duration_minutes' => 90,
            'slot_step_minutes' => 30,
            'min_booking_notice_minutes' => 60,
            'max_booking_horizon_days' => 30,
            'requires_confirmation' => false,
            'is_active' => true,
            'sort_weight' => 10,
        ]);
        $target = $wash->schedulingTarget;
        if ($target) {
            $target->update([
                'label' => $wash->title,
                'scheduling_enabled' => true,
                'internal_busy_enabled' => true,
                'calendar_usage_mode' => CalendarUsageMode::Disabled,
            ]);
            if (! $target->schedulingResources()->where('scheduling_resources.id', $res->id)->exists()) {
                $target->schedulingResources()->attach($res->id, [
                    'priority' => 0,
                    'is_default' => true,
                    'assignment_strategy' => AssignmentStrategy::FirstAvailable->value,
                ]);
            }
        }
        for ($d = 1; $d <= 6; $d++) {
            AvailabilityRule::query()->create([
                'scheduling_resource_id' => $res->id,
                'applies_to_scheduling_target_id' => null,
                'applies_to_bookable_service_id' => null,
                'rule_type' => AvailabilityRuleType::WeeklyOpen,
                'weekday' => $d,
                'starts_at_local' => '09:00',
                'ends_at_local' => '20:00',
                'is_active' => true,
            ]);
        }
        $long = BookableService::query()->create([
            'scheduling_scope' => SchedulingScope::Tenant,
            'tenant_id' => $tenantId,
            'slug' => 'interior-detailing-quote',
            'title' => 'Химчистка / долгий детейл (подтверждение)',
            'description' => 'Слоты не являются мгновенным подтверждением — менеджер согласует смену.',
            'duration_minutes' => 360,
            'slot_step_minutes' => 60,
            'requires_confirmation' => true,
            'is_active' => true,
            'sort_weight' => 20,
        ]);
        $t2 = $long->schedulingTarget;
        if ($t2) {
            $t2->update([
                'scheduling_enabled' => true,
                'internal_busy_enabled' => true,
                'calendar_usage_mode' => CalendarUsageMode::Disabled,
            ]);
            if (! $t2->schedulingResources()->where('scheduling_resources.id', $res->id)->exists()) {
                $t2->schedulingResources()->attach($res->id, [
                    'priority' => 0,
                    'is_default' => true,
                    'assignment_strategy' => AssignmentStrategy::FirstAvailable->value,
                ]);
            }
        }
    }
}
