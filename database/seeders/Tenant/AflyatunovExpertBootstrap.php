<?php

namespace Database\Seeders\Tenant;

use App\Http\Controllers\HomeController;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use Illuminate\Support\Facades\DB;

/**
 * Демо-контент expert-тенанта aflyatunov (страница home, секции, программы, FAQ и т.д.).
 * Вынесено из миграции: при уже существующем тенанте миграция не должна молча пропускать наполнение.
 */
final class AflyatunovExpertBootstrap
{
    public const SLUG = 'aflyatunov';

    /**
     * Короткий кинематографичный hero: один смысл, без лишнего абзаца в data.
     *
     * @return array<string, mixed>
     */
    private static function expertHeroCinematicCore(): array
    {
        return [
            'heading' => 'Уверенное вождение в городе, на парковке и в сложных условиях',
            'subheading' => 'Индивидуально с Маратом Афлятуновым, КМС по автоспорту. Новички и опытные водители — убираем страх, даём уверенность в городе и на парковке.',
            'description' => '',
            'primary_cta_label' => 'Записаться на занятие',
            'primary_cta_anchor' => '#expert-inquiry',
            'secondary_cta_label' => 'Посмотреть программы',
            'secondary_cta_anchor' => '/programs',
            'trust_badges' => [
                ['text' => 'На автомобиле клиента'],
                ['text' => 'Индивидуальный подход'],
                ['text' => 'Город / парковка / контраварийка'],
                ['text' => 'Челябинск и область'],
            ],
            'overlay_dark' => true,
            'video_trigger_label' => 'Смотреть, как проходят занятия',
        ];
    }

    /**
     * Блок «запросы»: короткие карточки + lead для секции.
     *
     * @param  callable(string): string  $u  {@see brandPublicUrl}
     * @return array<string, mixed>
     */
    private static function aflyatunovProblemCardsPayload(callable $u): array
    {
        return [
            'section_heading' => 'С какими запросами чаще всего приходят',
            'section_lead' => 'Без шаблонов: на практике разберём именно ваш сценарий.',
            'footnote' => 'Уровень, автомобиль и цели учитываем заранее.',
            'accent_image_url' => $u('process-accent.jpg'),
            'items' => [
                ['title' => 'Страх самостоятельной езды', 'description' => 'Права есть, а в поток одному страшно.', 'solution' => 'Спокойствие, понимание машины, уверенность в типовых манёврах.', 'is_featured' => true],
                ['title' => 'Парковка', 'description' => 'Зеркала, задний ход, тесные дворы.', 'solution' => 'Ориентиры и габариты до автоматизма.', 'is_featured' => false],
                ['title' => 'Плотный поток', 'description' => 'Перестроения и перекрёстки давят.', 'solution' => 'Читаем ситуацию, выбираем безопасный темп.', 'is_featured' => false],
                ['title' => 'Зима и гололёд', 'description' => 'Страх скользкого покрытия и заноса.', 'solution' => 'Контраварийка под ваш уровень.', 'is_featured' => false],
                ['title' => 'Автоспорт', 'description' => 'Трек, тайм-аттак — с чего начать.', 'solution' => 'Техника, безопасность, разбор попыток.', 'is_featured' => false],
            ],
        ];
    }

    /**
     * @param  callable(string): string  $u
     * @return list<array<string, mixed>>
     */
    private static function aflyatunovEditorialGalleryItems(callable $u): array
    {
        return [
            ['media_kind' => 'image', 'image_url' => $u('gallery-1.jpg'), 'caption' => 'Практика и рабочие сценарии'],
            ['media_kind' => 'video', 'video_url' => $u('video-intro.mp4'), 'poster_url' => $u('hero.jpg'), 'caption' => 'Фрагмент реального занятия'],
            ['media_kind' => 'image', 'image_url' => $u('process-accent.jpg'), 'caption' => 'Площадка и город'],
            ['media_kind' => 'image', 'image_url' => $u('gallery-3.jpg'), 'caption' => 'Зима и динамика'],
            ['media_kind' => 'image', 'image_url' => $u('gallery-2.jpg'), 'caption' => 'Спорт и награждения'],
        ];
    }

    /** Второй визуальный проход: лёгкие карточки запросов, видео в процессе и медиа-секции, герой-копирайт, parking не featured. */
    public static function patchAflyatunovExpertVisualPass2(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId <= 0) {
            return;
        }

        $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        $u = fn (string $f): string => self::brandPublicUrl($tenantId, $f);

        $patches = [
            'expert_hero' => [
                'subheading' => self::expertHeroCinematicCore()['subheading'],
            ],
            'problem_cards' => self::aflyatunovProblemCardsPayload($u),
            'process_steps' => [
                'aside_video_url' => $u('video-intro.mp4'),
                'aside_video_poster_url' => $u('process-accent.jpg'),
            ],
            'editorial_gallery' => [
                'section_heading' => 'Реальные кадры и видео с занятий',
                'section_lead' => 'Зима, город, упражнения и короткий ролик с практики — не стоковые картинки.',
                'items' => self::aflyatunovEditorialGalleryItems($u),
            ],
        ];

        foreach ($patches as $sectionKey => $merge) {
            $q = DB::table('page_sections')->where('tenant_id', $tenantId)->where('section_key', $sectionKey);
            if ($homePageId > 0 && in_array($sectionKey, ['expert_hero', 'problem_cards', 'process_steps', 'editorial_gallery'], true)) {
                $q->where('page_id', $homePageId);
            }
            foreach ($q->get() as $row) {
                $data = json_decode((string) $row->data_json, true) ?: [];
                $data = array_merge($data, $merge);
                DB::table('page_sections')->where('id', $row->id)->update([
                    'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('tenant_service_programs')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'parking')
            ->update(['is_featured' => false, 'updated_at' => now()]);

        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    /** Текст hero для уже развёрнутого тенанта (не трогает hero_video_url / poster). */
    public static function patchAflyatunovHeroCinematicCopy(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId <= 0) {
            return;
        }

        $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homePageId <= 0) {
            return;
        }

        $merge = self::expertHeroCinematicCore();

        foreach (DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $homePageId)
            ->where('section_key', 'expert_hero')
            ->get() as $row) {
            $data = json_decode((string) $row->data_json, true) ?: [];
            $data = array_merge($data, $merge);
            DB::table('page_sections')->where('id', $row->id)->update([
                'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    public static function run(): void
    {
        $tenantId = DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if (! $tenantId) {
            self::createFullTenant();

            return;
        }
        self::ensureDemoContent((int) $tenantId);
    }

    public static function ensureDemoContentForSlug(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId > 0) {
            self::ensureDemoContent($tenantId);
        }
    }

    public static function createFullTenant(): void
    {
        $planId = DB::table('plans')->value('id');
        $ownerId = DB::table('users')->value('id');

        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Марат Афлятунов',
            'slug' => self::SLUG,
            'brand_name' => 'Марат Афлятунов',
            'theme_key' => 'expert_auto',
            'status' => 'active',
            'timezone' => 'Asia/Yekaterinburg',
            'locale' => 'ru',
            'currency' => 'RUB',
            'country' => 'RU',
            'plan_id' => $planId,
            'owner_user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::insertDomains($tenantId);
        $now = now();
        $pageId = self::insertHomePage($tenantId, $now);
        self::insertHomeSections($pageId, $tenantId, $now);
        self::ensureExpertPublicBranding($tenantId);
        self::seedPrograms($tenantId, $now);
        self::seedReviews($tenantId, $now);
        self::seedFaqs($tenantId, $now);
        self::seedFormConfig($tenantId, $now);
        self::seedSeoMeta($tenantId, $pageId, $now);
        self::ensureExpertMenuPages($tenantId, $now);
        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    public static function ensureDemoContent(int $tenantId): void
    {
        $now = now();
        self::insertDomains($tenantId);
        $pageId = self::ensureHomePage($tenantId, $now);
        self::ensureHomeSections($pageId, $tenantId, $now);

        if (DB::table('tenant_service_programs')->where('tenant_id', $tenantId)->doesntExist()) {
            self::seedPrograms($tenantId, $now);
        }
        if (DB::table('reviews')->where('tenant_id', $tenantId)->doesntExist()) {
            self::seedReviews($tenantId, $now);
        }
        if (DB::table('faqs')->where('tenant_id', $tenantId)->doesntExist()) {
            self::seedFaqs($tenantId, $now);
        }
        if (DB::table('form_configs')->where('tenant_id', $tenantId)->where('form_key', 'expert_lead')->doesntExist()) {
            self::seedFormConfig($tenantId, $now);
        }
        if (DB::table('seo_meta')
            ->where('tenant_id', $tenantId)
            ->where('seoable_type', 'App\\Models\\Page')
            ->where('seoable_id', $pageId)
            ->doesntExist()) {
            self::seedSeoMeta($tenantId, $pageId, $now);
        }

        self::ensureExpertPublicBranding($tenantId);
        self::ensureExpertMenuPages($tenantId, $now);

        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    public static function ensureExpertMenuPagesForSlug(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId > 0) {
            self::ensureExpertMenuPages($tenantId, now());
        }
    }

    public static function ensureExpertPublicBrandingForSlug(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId > 0) {
            self::applyExpertPublicBrandingDefaults($tenantId, true);
        }
    }

    /**
     * Публичные бренд-изображения: ключ на диске {@code tenants/{id}/public/site/brand/{file}} (R2 / CDN через TenantStorage::publicUrl).
     */
    public static function brandPublicUrl(int $tenantId, string $file): string
    {
        $file = ltrim($file, '/');

        return TenantStorage::forTrusted($tenantId)->publicUrl('site/brand/'.$file);
    }

    private static function ensureExpertPublicBranding(int $tenantId): void
    {
        self::applyExpertPublicBrandingDefaults($tenantId, false);
    }

    /**
     * @param  bool  $force  true — перезаписать (миграция «приведи к шаблону»); false — только если пусто (новый тенант).
     */
    private static function applyExpertPublicBrandingDefaults(int $tenantId, bool $force): void
    {
        if ($force) {
            TenantSetting::setForTenant(
                $tenantId,
                'general.site_name',
                'Марат Афлятунов — инструктор по вождению',
            );
            TenantSetting::setForTenant(
                $tenantId,
                'branding.primary_color',
                '#c9a87c',
            );

            return;
        }

        $name = TenantSetting::getForTenant($tenantId, 'general.site_name', '');
        if (! is_string($name) || trim($name) === '') {
            TenantSetting::setForTenant(
                $tenantId,
                'general.site_name',
                'Марат Афлятунов — инструктор по вождению',
            );
        }
        $pc = TenantSetting::getForTenant($tenantId, 'branding.primary_color', '');
        if (! is_string($pc) || trim($pc) === '') {
            TenantSetting::setForTenant($tenantId, 'branding.primary_color', '#c9a87c');
        }
    }

    /**
     * Отдельные страницы в меню: программы, о тренере, отзывы, контакты.
     */
    private static function ensureExpertMenuPages(int $tenantId, $now): void
    {
        $defs = [
            ['slug' => 'programs', 'name' => 'Программы', 'order' => 10, 'factory' => fn (int $pid): array => self::programsPageSections($pid, $tenantId, $now)],
            ['slug' => 'o-trener', 'name' => 'О тренере', 'order' => 20, 'factory' => fn (int $pid): array => self::aboutTrainerPageSections($pid, $tenantId, $now)],
            ['slug' => 'otzyvy', 'name' => 'Отзывы', 'order' => 30, 'factory' => fn (int $pid): array => self::reviewsPageSections($pid, $tenantId, $now)],
            ['slug' => 'kontakty', 'name' => 'Контакты', 'order' => 40, 'factory' => fn (int $pid): array => self::contactsPageSections($pid, $tenantId, $now)],
        ];

        foreach ($defs as $def) {
            $exists = (int) DB::table('pages')
                ->where('tenant_id', $tenantId)
                ->where('slug', $def['slug'])
                ->value('id');
            if ($exists > 0) {
                DB::table('pages')->where('id', $exists)->update([
                    'show_in_main_menu' => true,
                    'main_menu_sort_order' => $def['order'],
                    'status' => 'published',
                    'updated_at' => $now,
                ]);

                continue;
            }

            $pageId = (int) DB::table('pages')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $def['name'],
                'slug' => $def['slug'],
                'template' => 'default',
                'status' => 'published',
                'published_at' => $now,
                'show_in_main_menu' => true,
                'main_menu_sort_order' => $def['order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($def['factory']($pageId) as $row) {
                DB::table('page_sections')->insert($row);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function programsPageSections(int $pageId, int $tenantId, $now): array
    {
        $order = 0;
        $mk = fn (string $key, string $type, array $data, ?string $title = null) => [
            'tenant_id' => $tenantId,
            'page_id' => $pageId,
            'section_key' => $key,
            'section_type' => $type,
            'title' => $title,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => ($order += 10),
            'is_visible' => true,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [
            $mk('service_program_cards', 'service_program_cards', [
                'section_heading' => 'Направления занятий',
                'section_id' => '',
                'limit' => 24,
                'layout' => 'grid',
            ], 'Программы'),
            $mk('expert_lead_form', 'expert_lead_form', [
                'heading' => 'Подберём программу под ваш запрос',
                'subheading' => 'Оставьте заявку — ответим и согласуем формат.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Записаться',
            ], 'Заявка'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function aboutTrainerPageSections(int $pageId, int $tenantId, $now): array
    {
        $order = 0;
        $mk = fn (string $key, string $type, array $data, ?string $title = null) => [
            'tenant_id' => $tenantId,
            'page_id' => $pageId,
            'section_key' => $key,
            'section_type' => $type,
            'title' => $title,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => ($order += 10),
            'is_visible' => true,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $u = fn (string $f): string => self::brandPublicUrl($tenantId, $f);

        return [
            $mk('founder_expert_bio', 'founder_expert_bio', [
                'heading' => 'О тренере',
                'lead' => 'Я Марат Афлятунов — многократный призёр и победитель соревнований по автоспорту в Челябинске и Челябинской области, КМС по автомобильному спорту.',
                'paragraphs' => [
                    ['text' => 'Эта работа для меня не про «покататься», а про то, чтобы дать человеку уверенность за рулём, понимание автомобиля и навык, который реально помогает в жизни и на дороге.'],
                    ['text' => 'На занятиях разбираем посадку, работу рулём и педалями, поведение автомобиля в разных условиях, парковку, маневрирование, движение в потоке и сложные дорожные ситуации.'],
                ],
                'photo_slot' => null,
                'section_id' => '',
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Марат Афлятунов',
                'trust_points' => [
                    ['text' => 'КМС по автоспорту'],
                    ['text' => 'Работа с новичками и опытными водителями'],
                    ['text' => 'Контраварийная подготовка и городское вождение'],
                    ['text' => 'Подготовка и сопровождение в автоспорте'],
                ],
                'cta_label' => 'Записаться на занятие',
                'cta_anchor' => '#expert-inquiry',
            ], 'О тренере'),
            $mk('credentials_grid', 'credentials_grid', [
                'section_heading' => 'Почему мне доверяют',
                'lead' => 'Реальный спортивный бэкграунд и спокойная подача — чтобы вы почувствовали контроль за рулём.',
                'items' => [
                    ['title' => 'КМС по автомобильному спорту', 'description' => 'Подтверждённая спортивная квалификация.'],
                    ['title' => 'Многократный призёр и победитель', 'description' => 'Соревнования в Челябинске и области.'],
                    ['title' => 'Практика в реальных условиях', 'description' => 'Город, парковка, погода — не только «площадка».'],
                    ['title' => 'Индивидуальная работа под запрос', 'description' => 'Маршруты, страхи, ваш автомобиль.'],
                    ['title' => 'Обучение на вашем автомобиле', 'description' => 'Привыкание к габаритам и поведению вашей машины.'],
                    ['title' => 'Спокойная и понятная подача', 'description' => 'Без давления — только ясные шаги.'],
                ],
                'background_media_slot' => null,
                'background_image_url' => $u('credentials-bg.jpg'),
                'supporting_image_url' => $u('portrait.jpg'),
                'supporting_image_alt' => 'Марат Афлятунов',
            ], 'Достижения'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function reviewsPageSections(int $pageId, int $tenantId, $now): array
    {
        $order = 0;
        $mk = fn (string $key, string $type, array $data, ?string $title = null) => [
            'tenant_id' => $tenantId,
            'page_id' => $pageId,
            'section_key' => $key,
            'section_type' => $type,
            'title' => $title,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => ($order += 10),
            'is_visible' => true,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [
            $mk('review_feed', 'review_feed', [
                'heading' => 'Отзывы учеников',
                'subheading' => 'Реальный опыт: парковка, город, зима, подготовка к стартам.',
                'section_id' => '',
                'layout' => 'grid',
                'limit' => 24,
            ], 'Отзывы'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function contactsPageSections(int $pageId, int $tenantId, $now): array
    {
        $order = 0;
        $mk = fn (string $key, string $type, array $data, ?string $title = null) => [
            'tenant_id' => $tenantId,
            'page_id' => $pageId,
            'section_key' => $key,
            'section_type' => $type,
            'title' => $title,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => ($order += 10),
            'is_visible' => true,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [
            $mk('contacts', 'contacts', [
                'heading' => 'Контакты',
                'phone' => '+7 (950) 731-76-84',
                'email' => 'Aflyatunov_m@mail.ru',
                'social_note' => 'aflyatunov_driving174',
            ], 'Контакты'),
            $mk('faq', 'faq', [
                'section_heading' => 'Частые вопросы',
                'source' => 'faqs_table',
            ], 'FAQ'),
            $mk('expert_lead_form', 'expert_lead_form', [
                'heading' => 'Записаться на занятие',
                'subheading' => 'Оставьте заявку — подберём время и формат.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Записаться',
            ], 'Заявка'),
        ];
    }

    /**
     * Обновляет data_json секций визуальными URL (фото из public/tenants/aflyatunov/).
     * Вызывается отдельной миграцией для уже созданных страниц.
     */
    public static function patchExpertVisualUrlsInDatabase(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId <= 0) {
            return;
        }

        $u = fn (string $f): string => self::brandPublicUrl($tenantId, $f);
        $patches = [
            'expert_hero' => [
                'hero_image_url' => $u('hero.jpg'),
                'hero_image_alt' => 'Марат Афлятунов у автомобиля — инструктор по вождению',
                'secondary_cta_anchor' => '/programs',
                'overlay_dark' => true,
            ],
            'credentials_grid' => [
                'background_image_url' => $u('credentials-bg.jpg'),
            ],
            'process_steps' => [
                'aside_image_url' => $u('process-accent.jpg'),
            ],
            'editorial_gallery' => [
                'section_lead' => 'Тренировки, трасса, зимняя практика и работа с автомобилем — реальные снимки с занятий.',
                'items' => [
                    ['image_url' => $u('gallery-1.jpg'), 'caption' => 'Практика и спортивный опыт'],
                    ['image_url' => $u('process-accent.jpg'), 'caption' => 'Работа за рулём и на площадке'],
                    ['image_url' => $u('gallery-3.jpg'), 'caption' => 'Зимняя и городская динамика'],
                ],
            ],
            'founder_expert_bio' => [
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Марат Афлятунов',
                'section_id' => 'about',
                'trust_points' => [
                    ['text' => 'КМС по автомобильному спорту'],
                    ['text' => 'Призёры и победители соревнований в регионе'],
                    ['text' => 'Индивидуальные занятия на вашем автомобиле'],
                    ['text' => 'Город, парковка, зима, контраварийка'],
                ],
            ],
            'review_feed' => [
                'subheading' => 'Реальный опыт учеников: парковка, город, зима, подготовка к стартам.',
                'section_id' => 'reviews',
            ],
        ];

        $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');

        foreach ($patches as $sectionKey => $merge) {
            $q = DB::table('page_sections')->where('tenant_id', $tenantId)->where('section_key', $sectionKey);
            if ($homePageId > 0 && $sectionKey === 'expert_lead_form') {
                $q->where('page_id', $homePageId);
            }
            foreach ($q->get() as $row) {
                $data = json_decode((string) $row->data_json, true) ?: [];
                $data = array_merge($data, $merge);
                DB::table('page_sections')->where('id', $row->id)->update([
                    'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    /**
     * Полный визуальный и текстовый слой лендинга + программы + демо-отзывы (для уже созданного тенанта).
     */
    public static function patchAflyatunovExpertDesignTz2026(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId <= 0) {
            return;
        }

        $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        $u = fn (string $f): string => self::brandPublicUrl($tenantId, $f);

        $patches = [
            'expert_hero' => array_merge(self::expertHeroCinematicCore(), [
                'hero_image_url' => $u('hero.jpg'),
                'hero_image_alt' => 'Марат Афлятунов у автомобиля — инструктор по вождению',
            ]),
            'problem_cards' => self::aflyatunovProblemCardsPayload($u),
            'credentials_grid' => [
                'section_heading' => 'Почему мне доверяют',
                'lead' => 'Реальный спортивный бэкграунд и спокойная подача — чтобы вы почувствовали контроль за рулём, а не «ещё один урок».',
                'items' => [
                    ['title' => 'КМС по автомобильному спорту', 'description' => 'Подтверждённая спортивная квалификация.'],
                    ['title' => 'Многократный призёр и победитель', 'description' => 'Соревнования в Челябинске и области.'],
                    ['title' => 'Практика в реальных условиях', 'description' => 'Город, парковка, погода — не только «площадка».'],
                    ['title' => 'Индивидуальная работа под запрос', 'description' => 'Маршруты, страхи, ваш автомобиль.'],
                    ['title' => 'Обучение на вашем автомобиле', 'description' => 'Привыкание к габаритам и поведению вашей машины.'],
                    ['title' => 'Спокойная и понятная подача', 'description' => 'Без давления и «крика» — только ясные шаги.'],
                ],
                'background_image_url' => $u('credentials-bg.jpg'),
                'supporting_image_url' => $u('portrait.jpg'),
                'supporting_image_alt' => 'Марат Афлятунов — инструктор',
            ],
            'process_steps' => [
                'aside_image_url' => $u('process-accent.jpg'),
                'aside_video_url' => $u('video-intro.mp4'),
                'aside_video_poster_url' => $u('process-accent.jpg'),
                'aside_title' => 'Почему занятие длится 3 часа',
                'aside_body' => 'Разбор запроса и посадки, работа над ошибками в динамике и закрепление — на вашем автомобиле, в городе или на площадке по задаче.',
                'steps' => [
                    ['title' => 'Вы оставляете заявку', 'body' => 'Пишете, с чем хотите поработать, какой опыт и машина.'],
                    ['title' => 'Согласуем формат', 'body' => 'Определяем цель: город, парковка, маршрут, зима, контраварийка или спорт.'],
                    ['title' => 'Бронь времени', 'body' => 'Слот согласуем заранее; бронь после предоплаты по правилам записи.'],
                    ['title' => 'Занятие на вашем автомобиле', 'body' => 'Адаптация к габаритам, педалям и обзору именно вашей машины.'],
                    ['title' => 'Разбор, практика, закрепление', 'body' => 'Три астрономических часа: от понимания к уверенным действиям.'],
                    ['title' => 'План дальше', 'body' => 'При необходимости — серия занятий и сезонная подготовка.'],
                ],
            ],
            'important_conditions' => [
                'section_heading' => 'Формат занятий и важные условия',
                'legal_note' => 'Ответственность за транспортное средство во время занятий несёт курсант. Задача инструктора — выстроить обучение максимально безопасно и осознанно.',
                'cards' => [
                    ['title' => 'Автомобиль', 'body' => 'Занятия проходят на автомобиле клиента — так быстрее привыкаете к габаритам и поведению своей машины.'],
                    ['title' => 'Длительность', 'body' => 'Одно занятие — 3 астрономических часа.'],
                    ['title' => 'Площадка и лёд', 'body' => 'При необходимости площадка или ледовый автодром оплачиваются отдельно — заранее согласуем.'],
                    ['title' => 'Бронь и отмена', 'body' => 'Бронирование после предоплаты. При отмене 50% предоплаты переносятся на следующее занятие по договорённости.'],
                ],
            ],
            'pricing_cards' => [
                'heading' => 'Стоимость — ориентиры без сюрпризов',
                'subheading' => 'Ниже — типовые позиции; точный формат уточним после короткого разговора о задаче.',
                'note' => 'Итоговый формат зависит от задач, сезона и необходимости аренды площадки.',
                'entry_point_slug' => 'single-session',
            ],
            'review_feed' => [
                'heading' => 'Отзывы учеников',
                'subheading' => 'Парковка, город, зима, контраварийка и спорт — реальные истории людей, с которыми мы работали на практике.',
                'section_id' => 'reviews',
            ],
            'editorial_gallery' => [
                'section_heading' => 'Реальные кадры и видео с занятий',
                'section_lead' => 'Зима, город, упражнения и короткий ролик с практики — не стоковые картинки.',
                'items' => self::aflyatunovEditorialGalleryItems($u),
            ],
            'founder_expert_bio' => [
                'heading' => 'О тренере',
                'lead' => 'Я Марат Афлятунов — многократный призёр и победитель соревнований по автоспорту в Челябинске и Челябинской области, КМС по автомобильному спорту.',
                'paragraphs' => [
                    ['text' => 'Эта работа для меня не про «покататься», а про то, чтобы дать человеку уверенность за рулём, понимание автомобиля и навык, который реально помогает в жизни и на дороге.'],
                    ['text' => 'На занятиях разбираем посадку, работу рулём и педалями, поведение автомобиля в разных условиях, парковку, маневрирование, движение в потоке и сложные дорожные ситуации.'],
                ],
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Марат Афлятунов',
                'section_id' => 'about',
                'trust_points' => [
                    ['text' => 'КМС по автоспорту'],
                    ['text' => 'Работа с новичками и опытными водителями'],
                    ['text' => 'Контраварийная подготовка и городское вождение'],
                    ['text' => 'Подготовка и сопровождение в автоспорте'],
                ],
                'cta_label' => 'Записаться на занятие',
                'cta_anchor' => '#expert-inquiry',
            ],
            'expert_lead_form' => [
                'heading' => 'Оставьте заявку — подберём формат занятия под ваш уровень и задачу',
                'subheading' => 'Парковка, город, зимнее вождение, разбор маршрута или уверенность за рулём — напишите, что важно сейчас.',
                'trust_chips' => [
                    ['text' => 'Индивидуальный подход'],
                    ['text' => 'На вашем автомобиле'],
                    ['text' => 'Реальная практика'],
                    ['text' => 'Челябинск и область'],
                ],
            ],
        ];

        foreach ($patches as $sectionKey => $merge) {
            $q = DB::table('page_sections')->where('tenant_id', $tenantId)->where('section_key', $sectionKey);
            if ($homePageId > 0 && in_array($sectionKey, ['expert_hero', 'problem_cards', 'process_steps', 'important_conditions', 'pricing_cards', 'review_feed', 'editorial_gallery', 'expert_lead_form'], true)) {
                $q->where('page_id', $homePageId);
            }
            foreach ($q->get() as $row) {
                $data = json_decode((string) $row->data_json, true) ?: [];
                $data = array_merge($data, $merge);
                DB::table('page_sections')->where('id', $row->id)->update([
                    'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        self::upsertAflyatunovServicePrograms($tenantId);
        DB::table('reviews')->where('tenant_id', $tenantId)->delete();
        self::seedReviews($tenantId, now());

        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    private static function jl(array $lines): string
    {
        return json_encode(array_values($lines), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tenantServiceProgramSeedRows(): array
    {
        return [
            [
                'slug' => 'single-session',
                'title' => 'Занятие по вождению',
                'teaser' => 'Точечная отработка навыка за одно занятие.',
                'description' => null,
                'program_type' => 'single_session',
                'price_amount' => 270000,
                'price_prefix' => 'от',
                'duration_label' => '3 ч',
                'format_label' => 'На вашем автомобиле',
                'audience_json' => self::jl(['Любой уровень: от первого выезда до точечной отработки навыка.']),
                'outcomes_json' => self::jl(['Понятный план занятия', 'Практика в городе или на площадке', 'Спокойный темп без давления']),
                'sort_order' => 5,
                'is_featured' => true,
            ],
            [
                'slug' => 'confidence',
                'title' => 'Уверенность за рулём',
                'teaser' => 'Права есть, а уверенности в потоке и на парковке не хватает.',
                'description' => null,
                'program_type' => 'program',
                'price_amount' => null,
                'price_prefix' => 'от',
                'duration_label' => 'серия занятий',
                'format_label' => 'Индивидуально · на вашем авто',
                'audience_json' => self::jl(['Для тех, кто получил права, но не чувствует уверенности в типовых сценариях.']),
                'outcomes_json' => self::jl(['Поток и перестроения', 'Габариты и обзор', 'Город и парковка']),
                'sort_order' => 10,
                'is_featured' => true,
            ],
            [
                'slug' => 'parking',
                'title' => 'Парковка',
                'teaser' => 'Задний ход, зеркала, дворы — без стресса.',
                'description' => null,
                'program_type' => 'program',
                'price_amount' => 1440000,
                'price_prefix' => null,
                'duration_label' => 'программа',
                'format_label' => 'Практика на вашем автомобиле',
                'audience_json' => self::jl(['Если парковка «съедает» внимание и времени уходит слишком много.']),
                'outcomes_json' => self::jl(['Задний ход и ориентиры', 'Зеркала и габариты', 'Тесные дворы и типовые парковки']),
                'sort_order' => 20,
                'is_featured' => false,
            ],
            [
                'slug' => 'city-driving',
                'title' => 'Городское движение',
                'teaser' => 'Плотный поток и сложные перекрёстки.',
                'description' => null,
                'program_type' => 'program',
                'price_amount' => 1440000,
                'price_prefix' => null,
                'duration_label' => 'программа',
                'format_label' => 'Реальный трафик',
                'audience_json' => self::jl(['Для тех, кто ездит, но хочет спокойнее чувствовать себя в плотном потоке.']),
                'outcomes_json' => self::jl(['Перестроения и выбор полосы', 'Сложные перекрёстки', 'Плотный поток без паники']),
                'sort_order' => 30,
                'is_featured' => false,
            ],
            [
                'slug' => 'counter-emergency',
                'title' => 'Контраварийная подготовка',
                'teaser' => 'Зима, лёд, занос и экстренное торможение.',
                'description' => null,
                'program_type' => 'program',
                'price_amount' => 1400000,
                'price_prefix' => null,
                'duration_label' => 'курс',
                'format_label' => 'Город / площадка по задаче',
                'audience_json' => self::jl(['Водителям с разным опытом, которые хотят понимать поведение машины на скользком покрытии.']),
                'outcomes_json' => self::jl(['Торможение и объезд', 'Занос, снос, стабилизация', 'Разные покрытия: лёд, снег, асфальт']),
                'sort_order' => 40,
                'is_featured' => true,
            ],
            [
                'slug' => 'route',
                'title' => 'Разбор маршрутов',
                'teaser' => 'Дом — работа, школа, «узкие» места.',
                'description' => null,
                'program_type' => 'route_training',
                'price_amount' => 960000,
                'price_prefix' => 'от',
                'duration_label' => 'по запросу',
                'format_label' => 'Ваши реальные маршруты',
                'audience_json' => self::jl(['Если каждый день повторяются одни и те же сложные участки.']),
                'outcomes_json' => self::jl(['Дом–работа и дом–школа', 'Сложные ежедневные сценарии', 'План безопасных решений']),
                'sort_order' => 50,
                'is_featured' => false,
            ],
            [
                'slug' => 'motorsport',
                'title' => 'Сопровождение в автоспорте',
                'teaser' => 'Любительские старты и техника безопасно.',
                'description' => null,
                'program_type' => 'sport_support',
                'price_amount' => null,
                'price_prefix' => null,
                'duration_label' => 'по запросу',
                'format_label' => 'Индивидуально',
                'audience_json' => self::jl(['Для тех, кто выходит на автоспринт, тайм-аттак или джимхану.']),
                'outcomes_json' => self::jl(['Подготовка к старту', 'Разбор техники и попыток', 'Сопровождение на событии']),
                'sort_order' => 60,
                'is_featured' => false,
            ],
        ];
    }

    private static function upsertAflyatunovServicePrograms(int $tenantId): void
    {
        $now = now();
        foreach (self::tenantServiceProgramSeedRows() as $r) {
            $slug = (string) $r['slug'];
            $payload = $r;
            unset($payload['slug']);
            $payload['updated_at'] = $now;
            $n = DB::table('tenant_service_programs')
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->update($payload);
            if ($n === 0) {
                DB::table('tenant_service_programs')->insert(array_merge($r, [
                    'tenant_id' => $tenantId,
                    'is_visible' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function candidateHosts(): array
    {
        $hosts = ['aflyatunov.rentbase.local', 'aflyatunov.local', '127.0.0.1'];
        $defaultHost = config('app.tenant_default_host');
        if (is_string($defaultHost) && $defaultHost !== '' && ! in_array($defaultHost, $hosts, true)) {
            array_unshift($hosts, $defaultHost);
        }

        return $hosts;
    }

    private static function insertDomains(int $tenantId): void
    {
        foreach (self::candidateHosts() as $i => $host) {
            if ($host === '' || DB::table('tenant_domains')->where('host', $host)->exists()) {
                continue;
            }
            DB::table('tenant_domains')->insert([
                'tenant_id' => $tenantId,
                'host' => $host,
                'type' => 'subdomain',
                'is_primary' => $i === 0,
                'status' => 'active',
                'ssl_status' => 'not_required',
                'verified_at' => now(),
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private static function insertHomePage(int $tenantId, $now): int
    {
        return (int) DB::table('pages')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => $now,
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function ensureHomePage(int $tenantId, $now): int
    {
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'home')
            ->value('id');

        if ($pageId > 0) {
            $publishedAt = DB::table('pages')->where('id', $pageId)->value('published_at') ?? $now;
            DB::table('pages')->where('id', $pageId)->update([
                'status' => 'published',
                'published_at' => $publishedAt,
                'updated_at' => $now,
            ]);

            return $pageId;
        }

        return self::insertHomePage($tenantId, $now);
    }

    private static function ensureHomeSections(int $pageId, int $tenantId, $now): void
    {
        $count = DB::table('page_sections')
            ->where('page_id', $pageId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('is_visible', true)
            ->where('section_key', '!=', 'main')
            ->count();

        if ($count > 0) {
            return;
        }

        self::insertHomeSections($pageId, $tenantId, $now);
    }

    private static function insertHomeSections(int $pageId, int $tenantId, $now): void
    {
        foreach (self::homeSectionsPayload($pageId, $tenantId, $now) as $row) {
            DB::table('page_sections')->insert($row);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function homeSectionsPayload(int $pageId, int $tenantId, $now): array
    {
        $order = 0;
        $mk = fn (string $key, string $type, array $data, ?string $title = null) => [
            'tenant_id' => $tenantId,
            'page_id' => $pageId,
            'section_key' => $key,
            'section_type' => $type,
            'title' => $title,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => ($order += 10),
            'is_visible' => true,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $u = fn (string $file): string => self::brandPublicUrl($tenantId, $file);

        return [
            $mk('expert_hero', 'expert_hero', array_merge(self::expertHeroCinematicCore(), [
                'hero_image_slot' => null,
                'hero_image_url' => $u('hero.jpg'),
                'hero_image_alt' => 'Марат Афлятунов у автомобиля — инструктор по вождению',
                'hero_video_url' => '',
                'hero_video_poster_url' => '',
            ]), 'Hero'),
            $mk('problem_cards', 'problem_cards', self::aflyatunovProblemCardsPayload($u)),
            $mk('credentials_grid', 'credentials_grid', [
                'section_heading' => 'Почему мне доверяют',
                'lead' => 'Реальный спортивный бэкграунд и спокойная подача — чтобы вы почувствовали контроль за рулём, а не «ещё один урок».',
                'items' => [
                    ['title' => 'КМС по автомобильному спорту', 'description' => 'Подтверждённая спортивная квалификация.'],
                    ['title' => 'Многократный призёр и победитель', 'description' => 'Соревнования в Челябинске и области.'],
                    ['title' => 'Практика в реальных условиях', 'description' => 'Город, парковка, погода — не только «площадка».'],
                    ['title' => 'Индивидуальная работа под запрос', 'description' => 'Маршруты, страхи, ваш автомобиль.'],
                    ['title' => 'Обучение на вашем автомобиле', 'description' => 'Привыкание к габаритам и поведению вашей машины.'],
                    ['title' => 'Спокойная и понятная подача', 'description' => 'Без давления и «крика» — только ясные шаги.'],
                ],
                'background_media_slot' => null,
                'background_image_url' => $u('credentials-bg.jpg'),
                'supporting_image_url' => $u('portrait.jpg'),
                'supporting_image_alt' => 'Марат Афлятунов — инструктор',
            ]),
            $mk('service_program_cards', 'service_program_cards', [
                'section_heading' => 'Направления занятий',
                'section_id' => 'programs',
                'limit' => 12,
                'layout' => 'grid',
            ]),
            $mk('process_steps', 'process_steps', [
                'section_heading' => 'Как проходит обучение',
                'aside_image_url' => $u('process-accent.jpg'),
                'aside_video_url' => $u('video-intro.mp4'),
                'aside_video_poster_url' => $u('process-accent.jpg'),
                'aside_title' => 'Почему занятие длится 3 часа',
                'aside_body' => 'Разбор запроса и посадки, работа над ошибками в динамике и закрепление — на вашем автомобиле, в городе или на площадке по задаче.',
                'steps' => [
                    ['title' => 'Вы оставляете заявку', 'body' => 'Пишете, с чем хотите поработать, какой опыт и машина.'],
                    ['title' => 'Согласуем формат', 'body' => 'Определяем цель: город, парковка, маршрут, зима, контраварийка или спорт.'],
                    ['title' => 'Бронь времени', 'body' => 'Слот согласуем заранее; бронь после предоплаты по правилам записи.'],
                    ['title' => 'Занятие на вашем автомобиле', 'body' => 'Адаптация к габаритам, педалям и обзору именно вашей машины.'],
                    ['title' => 'Разбор, практика, закрепление', 'body' => 'Три астрономических часа: от понимания к уверенным действиям.'],
                    ['title' => 'План дальше', 'body' => 'При необходимости — серия занятий и сезонная подготовка.'],
                ],
            ]),
            $mk('important_conditions', 'important_conditions', [
                'section_heading' => 'Формат занятий и важные условия',
                'legal_note' => 'Ответственность за транспортное средство во время занятий несёт курсант. Задача инструктора — выстроить обучение максимально безопасно и осознанно.',
                'cards' => [
                    ['title' => 'Автомобиль', 'body' => 'Занятия проходят на автомобиле клиента — так быстрее привыкаете к габаритам и поведению своей машины.'],
                    ['title' => 'Длительность', 'body' => 'Одно занятие — 3 астрономических часа.'],
                    ['title' => 'Площадка и лёд', 'body' => 'При необходимости площадка или ледовый автодром оплачиваются отдельно — заранее согласуем.'],
                    ['title' => 'Бронь и отмена', 'body' => 'Бронирование после предоплаты. При отмене 50% предоплаты переносятся на следующее занятие по договорённости.'],
                ],
            ]),
            $mk('pricing_cards', 'pricing_cards', [
                'heading' => 'Стоимость — ориентиры без сюрпризов',
                'subheading' => 'Ниже — типовые позиции; точный формат уточним после короткого разговора о задаче.',
                'layout' => 'grid',
                'note' => 'Итоговый формат зависит от задач, сезона и необходимости аренды площадки.',
                'entry_point_slug' => 'single-session',
            ]),
            $mk('review_feed', 'review_feed', [
                'heading' => 'Отзывы учеников',
                'subheading' => 'Парковка, город, зима, контраварийка и спорт — реальные истории людей, с которыми мы работали на практике.',
                'section_id' => 'reviews',
                'layout' => 'grid',
                'limit' => 12,
            ]),
            $mk('editorial_gallery', 'editorial_gallery', [
                'section_heading' => 'Реальные кадры и видео с занятий',
                'section_lead' => 'Зима, город, упражнения и короткий ролик с практики — не стоковые картинки.',
                'items' => self::aflyatunovEditorialGalleryItems($u),
            ]),
            $mk('founder_expert_bio', 'founder_expert_bio', [
                'heading' => 'О тренере',
                'lead' => 'Я Марат Афлятунов — многократный призёр и победитель соревнований по автоспорту в Челябинске и Челябинской области, КМС по автомобильному спорту.',
                'paragraphs' => [
                    ['text' => 'Эта работа для меня не про «покататься», а про то, чтобы дать человеку уверенность за рулём, понимание автомобиля и навык, который реально помогает в жизни и на дороге.'],
                    ['text' => 'На занятиях разбираем посадку, работу рулём и педалями, поведение автомобиля в разных условиях, парковку, маневрирование, движение в потоке и сложные дорожные ситуации.'],
                ],
                'photo_slot' => null,
                'section_id' => 'about',
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Марат Афлятунов',
                'trust_points' => [
                    ['text' => 'КМС по автоспорту'],
                    ['text' => 'Работа с новичками и опытными водителями'],
                    ['text' => 'Контраварийная подготовка и городское вождение'],
                    ['text' => 'Подготовка и сопровождение в автоспорте'],
                ],
                'cta_label' => 'Записаться на занятие',
                'cta_anchor' => '#expert-inquiry',
            ]),
            $mk('faq', 'faq', [
                'section_heading' => 'Частые вопросы',
                'source' => 'faqs_table',
            ]),
            $mk('expert_lead_form', 'expert_lead_form', [
                'heading' => 'Оставьте заявку — подберём формат занятия под ваш уровень и задачу',
                'subheading' => 'Парковка, город, зимнее вождение, разбор маршрута или уверенность за рулём — напишите, что важно сейчас.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Записаться',
                'trust_chips' => [
                    ['text' => 'Индивидуальный подход'],
                    ['text' => 'На вашем автомобиле'],
                    ['text' => 'Реальная практика'],
                    ['text' => 'Челябинск и область'],
                ],
            ]),
            $mk('contacts', 'contacts', [
                'heading' => 'Контакты',
                'phone' => '+7 (950) 731-76-84',
                'email' => 'Aflyatunov_m@mail.ru',
                'social_note' => 'aflyatunov_driving174',
            ]),
        ];
    }

    private static function seedPrograms(int $tenantId, $now): void
    {
        foreach (self::tenantServiceProgramSeedRows() as $r) {
            DB::table('tenant_service_programs')->insert(array_merge($r, [
                'tenant_id' => $tenantId,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private static function seedReviews(int $tenantId, $now): void
    {
        $samples = [
            ['name' => 'Татьяна Мастер', 'category_key' => 'confidence', 'headline' => 'Страх после 15 лет без езды', 'text_short' => 'Вернула уверенность и спокойствие в городе.', 'text_long' => 'Долго не садилась за руль. За несколько занятий стало спокойнее: понятно, что делать в потоке и на парковке, без ощущения, что меня «давят» со всех сторон.', 'text' => '', 'is_featured' => true, 'sort_order' => 10],
            ['name' => 'Татьяна Демьяненко', 'category_key' => 'parking', 'headline' => 'Парковка, маршрут, уверенность', 'text_short' => 'Парковка перестала быть главным стрессом.', 'text_long' => 'Разобрали парковку и привычный маршрут. Стало заметно проще заезжать во двор и чувствовать габариты — наконец-то езжу без лишней тревоги.', 'text' => '', 'is_featured' => true, 'sort_order' => 20],
            ['name' => 'Алексей Демченко', 'category_key' => 'motorsport', 'headline' => 'Автоспорт и результат', 'text_short' => 'Помог с подготовкой к стартам и техникой.', 'text_long' => 'Готовился к любительским стартам: разобрали траекторию, торможение и работу с машиной. Есть ощущение, что еду осознанно, а не «на удачу».', 'text' => '', 'is_featured' => true, 'sort_order' => 30],
            ['name' => 'Олечка Кирпичникова', 'category_key' => 'counter-emergency', 'headline' => 'Контраварийка и зима', 'text_short' => 'На льду стало понятнее, что делать с заносом.', 'text_long' => 'Боялась зимы и скользкого покрытия. После занятий поняла базовые действия при заносе и торможении — меньше паники, больше контроля.', 'text' => '', 'is_featured' => false, 'sort_order' => 40],
        ];

        foreach ($samples as $s) {
            DB::table('reviews')->insert(array_merge($s, [
                'tenant_id' => $tenantId,
                'city' => 'Челябинск',
                'rating' => 5,
                'media_type' => 'text',
                'video_url' => null,
                'meta_json' => null,
                'motorcycle_id' => null,
                'date' => $now->toDateString(),
                'source' => 'site',
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private static function seedFaqs(int $tenantId, $now): void
    {
        $qs = [
            ['На чьём автомобиле проходят занятия?', 'Занятия проходят на автомобиле клиента.'],
            ['Подойдёт ли мне, если есть права, но почти не езжу?', 'Да, формат как раз для восстановления навыка и уверенности.'],
            ['Можно ли заниматься только парковкой?', 'Да, программу можно сфокусировать на парковке и габаритах.'],
            ['Можно ли отработать конкретный маршрут?', 'Да, возможен разбор ваших маршрутов и сложных участков.'],
            ['Подходит ли контраварийка новичкам?', 'Да, подаётся с учётом уровня и задач.'],
            ['Где проходят занятия?', 'В реальных городских условиях; при необходимости — площадки по договорённости.'],
            ['Сколько длится одно занятие?', 'Одно занятие — 3 астрономических часа.'],
            ['Нужна ли предоплата?', 'Бронь времени согласуется после предоплаты по правилам, о которых сообщим при записи.'],
            ['Нужно ли ждать зимы для полезной подготовки?', 'Нет, много навыков отрабатывается круглый год.'],
            ['Можно ли подготовиться к соревнованиям?', 'Да, есть формат сопровождения в автоспорте.'],
        ];
        $sort = 0;
        foreach ($qs as [$q, $a]) {
            DB::table('faqs')->insert([
                'tenant_id' => $tenantId,
                'question' => $q,
                'answer' => $a,
                'category' => null,
                'sort_order' => ($sort += 10),
                'status' => 'published',
                'show_on_home' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private static function seedFormConfig(int $tenantId, $now): void
    {
        DB::table('form_configs')->insert([
            'tenant_id' => $tenantId,
            'form_key' => 'expert_lead',
            'title' => 'Запись на занятие',
            'description' => 'Экспертная заявка',
            'is_enabled' => true,
            'recipient_email' => null,
            'success_message' => 'Спасибо! Заявка отправлена. Мы свяжемся с вами, чтобы уточнить детали и подобрать формат занятия.',
            'error_message' => 'Не удалось отправить заявку. Попробуйте позже.',
            'fields_json' => json_encode([
                'goal_text' => ['label' => 'Что хотите улучшить', 'required' => true],
                'preferred_schedule' => ['label' => 'Удобное время', 'required' => false],
                'district' => ['label' => 'Район', 'required' => false],
                'has_own_car' => ['label' => 'Свой автомобиль', 'required' => false],
                'transmission' => ['label' => 'Коробка передач', 'required' => false],
                'has_license' => ['label' => 'Есть ВУ', 'required' => false],
                'comment' => ['label' => 'Комментарий', 'required' => false],
            ]),
            'settings_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function seedSeoMeta(int $tenantId, int $pageId, $now): void
    {
        $person = [
            '@type' => 'Person',
            'name' => 'Марат Афлятунов',
            'jobTitle' => 'Инструктор по вождению',
            'description' => 'Индивидуальные занятия по вождению в Челябинске.',
        ];
        $service = [
            '@type' => 'Service',
            'name' => 'Индивидуальные занятия по вождению',
            'areaServed' => 'Челябинск',
            'provider' => ['@type' => 'Person', 'name' => 'Марат Афлятунов'],
        ];

        DB::table('seo_meta')->insert([
            'tenant_id' => $tenantId,
            'seoable_type' => 'App\\Models\\Page',
            'seoable_id' => $pageId,
            'meta_title' => 'Инструктор по вождению в Челябинске — парковка, город, контраварийка | Марат Афлятунов',
            'meta_description' => 'Индивидуальные занятия по вождению в Челябинске: парковка, уверенность в городе, зимнее вождение, контраварийная подготовка. Занятия на вашем автомобиле.',
            'meta_keywords' => null,
            'h1' => 'Инструктор по вождению и контраварийной подготовке в Челябинске',
            'canonical_url' => null,
            'robots' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'is_indexable' => true,
            'is_followable' => true,
            'json_ld' => json_encode([$person, $service]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
