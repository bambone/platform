<?php

namespace Database\Seeders\Tenant;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelsStore;
use App\Filament\Platform\Resources\TenantResource\Pages\CreateTenant;
use App\Http\Controllers\HomeController;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageArea;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Демо-контент тенанта dementiev: тема advocate_editorial, страницы адвоката, FAQ, формы, SEO.
 *
 * Для `php artisan db:seed --class=…` используйте {@see \Database\Seeders\DementievAdvocateBootstrapSeeder} (это не Seeder).
 */
final class DementievAdvocateBootstrap
{
    public const SLUG = 'dementiev';

    /**
     * Официальная строка адреса офиса (как на Яндекс.Картах): индекс 464016, ул. Братьев Кашириных с заглавной К.
     */
    private const OFFICE_ADDRESS_LINE = 'Адрес офиса: 464016, г. Челябинск, ул. Братьев Кашириных, д. 85 «А», оф. 1';

    /**
     * Ориентир по карте для здания 85А (Братьев Кашириных, Челябинск); совпадает с геокодом по адресу.
     */
    private const OFFICE_MAP_LON = '61.3703791';

    private const OFFICE_MAP_LAT = '55.1770289';

    private const BRAND_FILES = [
        'hero.jpg',
        'portrait.jpg',
        'credentials-bg.jpg',
        'process-accent.jpg',
        'gallery-1.jpg',
        'gallery-2.jpg',
        'gallery-3.jpg',
        'logo-mark.png',
        'logo-header.png',
        'favicon-scales.png',
        'favicon-16.png',
        'favicon-32.png',
        'favicon.ico',
        'apple-touch-icon.png',
        'logo-mark.svg',
    ];

    public static function brandPublicUrl(int $tenantId, string $file): string
    {
        $file = ltrim($file, '/');

        return TenantStorage::forTrusted($tenantId)->publicUrl('site/brand/'.$file);
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

    public static function rollback(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
        if ($tenantId <= 0) {
            return;
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

    public static function ensureDemoContent(int $tenantId): void
    {
        $now = now();
        self::insertDomains($tenantId);
        self::syncBrandAssetsFromDocs($tenantId);
        self::normalizeBrandUrlsInPageSections($tenantId);
        self::patchDementievHomeBrandImageUrls($tenantId);
        self::seedContactChannels($tenantId);
        self::applyBrandingSettings($tenantId);
        $homeId = self::ensureHomePage($tenantId, $now);
        self::ensureHomeSections($homeId, $tenantId, $now);
        self::ensureInnerPages($tenantId, $now);
        self::seedFaqsIfEmpty($tenantId, $now);
        self::ensureFormConfig($tenantId, $now);
        self::seedSeoRecords($tenantId, $now);
        HomeController::forgetCachedPayloadForTenant($tenantId);
        self::ensureStorageQuota($tenantId);
    }

    private static function createFullTenant(): void
    {
        $planId = DB::table('plans')->value('id');
        $ownerId = DB::table('users')->value('id');

        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Дементьев Никита Владимирович',
            'slug' => self::SLUG,
            'brand_name' => 'Адвокат Дементьев Н. В.',
            'theme_key' => 'advocate_editorial',
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
        self::syncBrandAssetsFromDocs($tenantId);
        self::normalizeBrandUrlsInPageSections($tenantId);
        self::seedContactChannels($tenantId);
        self::applyBrandingSettings($tenantId);

        $now = now();
        $homeId = self::insertHomePage($tenantId, $now);
        foreach (self::homeSectionsPayload($homeId, $tenantId, $now) as $row) {
            DB::table('page_sections')->insert($row);
        }
        self::patchDementievHomeBrandImageUrls($tenantId);
        self::ensureInnerPages($tenantId, $now);
        self::seedFaqsIfEmpty($tenantId, $now);
        self::ensureFormConfig($tenantId, $now);
        self::seedSeoRecords($tenantId, $now);
        HomeController::forgetCachedPayloadForTenant($tenantId);
        self::ensureStorageQuota($tenantId);
    }

    /**
     * Как в {@see CreateTenant}: без записи квоты клиент «неполный» в консоли и в фильтрах по хранилищу.
     */
    private static function ensureStorageQuota(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }
        app(TenantStorageQuotaService::class)->ensureQuotaRecord($tenant);
    }

    private static function syncBrandAssetsFromDocs(int $tenantId): void
    {
        $dir = base_path('docs/tenants_tz/aflyatunov/dementiev-media');
        if (! is_dir($dir)) {
            return;
        }
        $storage = TenantStorage::forTrusted($tenantId);
        foreach (self::BRAND_FILES as $name) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            if (! is_file($path)) {
                continue;
            }
            $storage->putInArea(TenantStorageArea::PublicSite, 'brand/'.$name, File::get($path));
        }
    }

    /**
     * В JSON секций заменяет устаревшие полные URL на стабильные относительные {@code site/brand/…}
     * (тот же файл при смене tenant_id / домена резолвится через TenantPublicAssetResolver).
     */
    private static function normalizeBrandUrlsInPageSections(int $tenantId): void
    {
        $rows = DB::table('page_sections')->where('tenant_id', $tenantId)->get(['id', 'data_json']);
        foreach ($rows as $row) {
            $data = json_decode((string) $row->data_json, true);
            if (! is_array($data)) {
                continue;
            }
            $next = self::normalizeBrandUrlsInData($data);
            $encoded = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === (string) $row->data_json) {
                continue;
            }
            DB::table('page_sections')->where('id', (int) $row->id)->update([
                'data_json' => $encoded,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeBrandUrlsInData(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = self::normalizeBrandUrlsInData($v);

                continue;
            }
            if (! is_string($v) || $v === '') {
                $out[$k] = $v;

                continue;
            }
            $file = self::matchKnownBrandFileBasename($v);
            if ($file !== null) {
                $out[$k] = 'site/brand/'.$file;

                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    private static function matchKnownBrandFileBasename(string $value): ?string
    {
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) ? $path : $value;
        $base = basename(str_replace('\\', '/', $path));
        foreach (self::BRAND_FILES as $name) {
            if (strcasecmp($base, $name) === 0) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Главная: принудительно проставляет относительные URL бренд-файлов (hero, портрет, галерея).
     * Нужно, если секции уже были в БД с пустыми/битыми полями — ensureHomeSections их не перезаписывает.
     */
    private static function patchDementievHomeBrandImageUrls(int $tenantId): void
    {
        $homeId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'home')
            ->value('id');
        if ($homeId <= 0) {
            return;
        }

        $u = fn (string $f): string => 'site/brand/'.ltrim($f, '/');

        $patches = [
            'expert_hero' => [
                'hero_image_url' => $u('hero.jpg'),
                'hero_image_alt' => 'Дементьев Никита Владимирович — адвокат',
                'subheading' => 'Член Адвокатской палаты Челябинской области. Гражданские, арбитражные и уголовные дела; отдельная компетенция — суд присяжных и защита иностранных граждан.',
            ],
            'problem_cards' => [
                'accent_image_url' => $u('process-accent.jpg'),
            ],
            'credentials_grid' => [
                'background_image_url' => $u('credentials-bg.jpg'),
                'supporting_image_url' => $u('portrait.jpg'),
                'supporting_image_alt' => 'Дементьев Никита Владимирович',
            ],
            'founder_expert_bio' => [
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Дементьев Никита Владимирович',
            ],
            'process_steps' => [
                'aside_image_url' => $u('portrait.jpg'),
            ],
        ];

        foreach ($patches as $sectionKey => $patch) {
            $row = DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $homeId)
                ->where('section_key', $sectionKey)
                ->first();
            if ($row === null) {
                continue;
            }
            $data = json_decode((string) $row->data_json, true) ?: [];
            $data = array_merge($data, $patch);
            DB::table('page_sections')->where('id', (int) $row->id)->update([
                'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }

        $gallery = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $homeId)
            ->where('section_key', 'editorial_gallery')
            ->first();
        if ($gallery === null) {
            return;
        }
        $data = json_decode((string) $gallery->data_json, true) ?: [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $files = ['gallery-1.jpg', 'gallery-2.jpg', 'gallery-3.jpg'];
        foreach ($files as $i => $file) {
            if (! isset($items[$i]) || ! is_array($items[$i])) {
                continue;
            }
            $items[$i]['media_kind'] = 'image';
            $items[$i]['image_url'] = $u($file);
        }
        $data['items'] = $items;
        DB::table('page_sections')->where('id', (int) $gallery->id)->update([
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    private static function seedContactChannels(int $tenantId): void
    {
        app(TenantContactChannelsStore::class)->persist($tenantId, [
            ContactChannelType::Phone->value => [
                'uses_channel' => true,
                'public_visible' => true,
                'allowed_in_forms' => true,
                'business_value' => '+7 (965) 853-44-83',
                'sort_order' => 10,
            ],
            ContactChannelType::Whatsapp->value => [
                'uses_channel' => false,
                'public_visible' => false,
                'allowed_in_forms' => false,
                'business_value' => '',
                'sort_order' => 20,
            ],
            ContactChannelType::Telegram->value => [
                'uses_channel' => false,
                'public_visible' => false,
                'allowed_in_forms' => false,
                'business_value' => '',
                'sort_order' => 30,
            ],
            ContactChannelType::Vk->value => [
                'uses_channel' => true,
                'public_visible' => true,
                'allowed_in_forms' => true,
                'business_value' => 'https://vk.com/sudjury74',
                'sort_order' => 40,
            ],
            ContactChannelType::Max->value => [
                'uses_channel' => false,
                'public_visible' => false,
                'allowed_in_forms' => false,
                'business_value' => '',
                'sort_order' => 50,
            ],
        ]);
    }

    private static function applyBrandingSettings(int $tenantId): void
    {
        TenantSetting::setForTenant($tenantId, 'general.site_name', 'Дементьев Никита Владимирович — адвокат', 'string');
        TenantSetting::setForTenant($tenantId, 'general.primary_city', 'Челябинск', 'string');
        TenantSetting::setForTenant($tenantId, 'branding.primary_color', '#9a7b4f', 'string');

        $ts = TenantStorage::forTrusted($tenantId);

        $logoMarkRel = 'site/brand/logo-mark.png';
        $logoHeaderMarkRel = 'site/brand/logo-mark-header.png';
        $logoFallbackRel = 'site/brand/logo-header.png';
        if ($ts->existsPublic($logoMarkRel)) {
            TenantSetting::setForTenant($tenantId, 'branding.logo_path', $ts->publicPath($logoMarkRel), 'string');
            TenantSetting::setForTenant($tenantId, 'branding.logo', $ts->publicUrl($logoMarkRel), 'string');
        } elseif ($ts->existsPublic($logoHeaderMarkRel)) {
            TenantSetting::setForTenant($tenantId, 'branding.logo_path', $ts->publicPath($logoHeaderMarkRel), 'string');
            TenantSetting::setForTenant($tenantId, 'branding.logo', $ts->publicUrl($logoHeaderMarkRel), 'string');
        } elseif ($ts->existsPublic($logoFallbackRel)) {
            TenantSetting::setForTenant($tenantId, 'branding.logo_path', $ts->publicPath($logoFallbackRel), 'string');
            TenantSetting::setForTenant($tenantId, 'branding.logo', $ts->publicUrl($logoFallbackRel), 'string');
        }

        $favicon32Rel = 'site/brand/favicon-32.png';
        $faviconFallbackRel = 'site/brand/favicon-scales.png';
        if ($ts->existsPublic($favicon32Rel)) {
            TenantSetting::setForTenant($tenantId, 'branding.favicon_path', $ts->publicPath($favicon32Rel), 'string');
            TenantSetting::setForTenant($tenantId, 'branding.favicon', $ts->publicUrl($favicon32Rel), 'string');
        } else        if ($ts->existsPublic($faviconFallbackRel)) {
            TenantSetting::setForTenant($tenantId, 'branding.favicon_path', $ts->publicPath($faviconFallbackRel), 'string');
            TenantSetting::setForTenant($tenantId, 'branding.favicon', $ts->publicUrl($faviconFallbackRel), 'string');
        }

        TenantSetting::setForTenant($tenantId, 'contacts.email', 'adv174ur@gmail.com', 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.public_office_address', 'г. Челябинск, ул. Братьев Кашириных, д. 85А, офис 1', 'string');
        TenantSetting::setForTenant($tenantId, 'public_site.footer_brand_blurb', 'Персональный сайт адвоката по гражданским, арбитражным и уголовным делам.'."\n".'Отдельная компетенция — дела с участием присяжных и защита прав иностранных граждан.', 'string');
        TenantSetting::setForTenant($tenantId, 'public_site.footer_approach_line', 'Спокойная правовая стратегия, процессуальная дисциплина и персональная работа по делу.', 'string');
        TenantSetting::setForTenant($tenantId, 'public_site.footer_legal_disclaimer', 'Информация на сайте носит справочный характер и не является публичной офертой.', 'string');
    }

    /**
     * @return list<string>
     */
    private static function candidateHosts(): array
    {
        $hosts = ['dementiev.rentbase.local', 'dementiev.local', '127.0.0.1'];
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
            DB::table('pages')->where('id', $pageId)->update([
                'status' => 'published',
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
            ->where('section_key', '!=', 'main')
            ->count();
        if ($count > 0) {
            return;
        }
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
        $u = fn (string $f): string => 'site/brand/'.ltrim($f, '/');

        return [
            $mk('expert_hero', 'expert_hero', [
                'heading' => 'Дементьев Никита Владимирович',
                'hero_eyebrow' => 'Адвокат • Челябинск и область',
                'subheading' => 'Член Адвокатской палаты Челябинской области. Гражданские, арбитражные и уголовные дела; отдельная компетенция — суд присяжных и защита иностранных граждан.',
                'description' => '',
                'primary_cta_label' => 'Записаться на консультацию',
                'primary_cta_anchor' => '#expert-inquiry',
                'secondary_cta_label' => 'Обсудить ситуацию',
                'secondary_cta_anchor' => '/contacts',
                'trust_badges' => [
                    ['text' => 'Рег. № 74/1764'],
                    ['text' => 'Практика с 2011 года'],
                    ['text' => 'Коллегия «Защитник»'],
                    ['text' => 'Челябинск'],
                ],
                'overlay_dark' => true,
                'hero_image_slot' => null,
                'hero_image_url' => $u('hero.jpg'),
                'hero_image_alt' => 'Дементьев Никита Владимирович — адвокат',
                'hero_video_url' => '',
                'hero_video_poster_url' => '',
            ], 'Hero'),
            $mk('problem_cards', 'problem_cards', [
                'section_heading' => 'Направления практики',
                'section_lead' => 'Сфокусированная работа по делам, где важны стратегия, процессуальная дисциплина и устойчивая правовая позиция.',
                'footnote' => 'Первичный контакт — короткий разговор и понимание сроков; без обещаний результата.',
                'accent_image_url' => $u('process-accent.jpg'),
                'items' => [
                    ['title' => 'Уголовные дела', 'description' => 'Защита на всех стадиях: проверка, следствие, суд.', 'solution' => 'Оценка рисков, выбор тактики, сопровождение процедуры.', 'is_featured' => true],
                    ['title' => 'Суд присяжных', 'description' => 'Отдельная подготовка и ведение дел с участием коллегии присяжных.', 'solution' => 'Структура защиты под формат суда присяжных.', 'is_featured' => true],
                    ['title' => 'Гражданские споры', 'description' => 'Семья, земля, договоры и иные гражданские категории.', 'solution' => 'Претензионная работа и судебное представительство.', 'is_featured' => false],
                    ['title' => 'Арбитраж', 'description' => 'Корпоративные и хозяйственные споры.', 'solution' => 'Позиция и доказательственная база под арбитражную процедуру.', 'is_featured' => false],
                    ['title' => 'Потерпевшие', 'description' => 'Представление интересов потерпевших.', 'solution' => 'Правовая поддержка и процессуальное участие.', 'is_featured' => false],
                    ['title' => 'Иностранные граждане', 'description' => 'Миграционные вопросы и защита прав.', 'solution' => 'Сопровождение в конфликтных ситуациях.', 'is_featured' => false],
                ],
            ], 'Практики'),
            $mk('credentials_grid', 'credentials_grid', [
                'section_heading' => 'Доверие и статус',
                'lead' => 'Публично проверяемые сведения о статусе и опыте — без «топов» и несуществующих наград.',
                'items' => [
                    ['title' => 'Адвокат АП Челябинской области', 'description' => 'Регистрационный номер 74/1764, статус действующий.'],
                    ['title' => 'Практика с 2011 года', 'description' => 'Профессиональный путь в адвокатуре и коллегии.'],
                    ['title' => 'Коллегия «Защитник»', 'description' => 'Членство в адвокатской коллегии.'],
                    ['title' => 'Образование', 'description' => 'Подготовка в ведущих юридических вузах (в т.ч. с отличием).'],
                    ['title' => 'Проект JuryLab', 'description' => 'Образовательное и методическое направление по теме суда присяжных.'],
                    ['title' => 'Повышение квалификации', 'description' => 'Регулярное участие в профильных мероприятиях палаты и ФПА.'],
                ],
                'background_media_slot' => null,
                'background_image_url' => $u('credentials-bg.jpg'),
                'supporting_image_url' => $u('portrait.jpg'),
                'supporting_image_alt' => 'Дементьев Никита Владимирович',
            ], 'Статус'),
            $mk('founder_expert_bio', 'founder_expert_bio', [
                'heading' => 'Подход к защите',
                'lead' => 'Работа строится на спокойной стратегии, ясной коммуникации и процессуальной дисциплине.',
                'paragraphs' => [
                    ['text' => 'Задача адвоката — не «обещать победу», а выстроить понятный план действий и защищать интересы доверителя всеми законными средствами.'],
                    ['text' => 'В каждом деле важны факты, сроки и доказательства; я ориентируюсь на реальную картину и допустимые процессуальные инструменты.'],
                ],
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Дементьев Никита Владимирович',
                'section_id' => 'about-philosophy',
                'trust_points' => [
                    ['text' => 'Стратегия и риски'],
                    ['text' => 'Процессуальная дисциплина'],
                    ['text' => 'Сдержанный профессиональный тон'],
                    ['text' => 'Фокус на интересах доверителя'],
                ],
                'cta_label' => 'Обсудить ситуацию',
                'cta_anchor' => '#expert-inquiry',
            ], 'Философия'),
            $mk('important_conditions', 'important_conditions', [
                'section_heading' => 'Суд присяжных и экспертиза',
                'legal_note' => 'Отдельная компетенция — подготовка и ведение дел с участием присяжных заседателей; образовательный проект «Лаборатория суда присяжных» и JuryLab как методическая база.',
                'cards' => [
                    ['title' => 'Почему это отдельная работа', 'body' => 'Формат коллегии присяжных требует иной структуры доказательств и коммуникации с судом.'],
                    ['title' => 'Обучение и практика', 'body' => 'Регулярное повышение квалификации и участие в профильных мероприятиях палаты.'],
                    ['title' => 'Проект JuryLab', 'body' => 'Методическая и образовательная линия, усиливающая экспертизу в этой категории дел.'],
                    ['title' => 'Дальнейшие шаги', 'body' => 'Подробнее — на странице о суде присяжных; первичный контакт через форму консультации.'],
                ],
            ], 'Присяжные'),
            $mk('process_steps', 'process_steps', [
                'section_heading' => 'Как мы работаем',
                'aside_image_url' => $u('portrait.jpg'),
                'aside_video_url' => '',
                'aside_video_poster_url' => '',
                'aside_title' => 'Понятный процесс',
                'aside_body' => 'Без давления и навязанных решений: сначала ясность по ситуации и возможным шагам.',
                'steps' => [
                    ['title' => 'Первичная связь', 'body' => 'Короткий контакт по телефону, почте или форме: тема и срочность.'],
                    ['title' => 'Изучение документов', 'body' => 'Анализ имеющихся материалов и обстоятельств.'],
                    ['title' => 'Правовая позиция', 'body' => 'Формулировка рабочей позиции и рисков.'],
                    ['title' => 'Стратегия', 'body' => 'План действий с учётом процессуальных сроков.'],
                    ['title' => 'Сопровождение', 'body' => 'Переговоры, следствие, суд — по этапам дела.'],
                    ['title' => 'Дальнейшая защита', 'body' => 'Поддержка интересов доверителя по мере развития ситуации.'],
                ],
            ], 'Процесс'),
            $mk('editorial_gallery', 'editorial_gallery', [
                'section_heading' => 'Профессиональный контекст',
                'section_lead' => 'Рабочие материалы и публичная деятельность — без стоковых клише.',
                'items' => [
                    ['media_kind' => 'image', 'image_url' => $u('gallery-1.jpg'), 'caption' => 'Деловой контекст'],
                    ['media_kind' => 'image', 'image_url' => $u('gallery-2.jpg'), 'caption' => 'Практика и взаимодействие'],
                    ['media_kind' => 'image', 'image_url' => $u('gallery-3.jpg'), 'caption' => 'Челябинск'],
                ],
            ], 'Медиа'),
            $mk('faq', 'faq', [
                'section_heading' => 'Вопросы до консультации',
                'source' => 'faqs_table',
            ], 'FAQ'),
            $mk('expert_lead_form', 'expert_lead_form', [
                'heading' => 'Запросить консультацию',
                'subheading' => 'Опишите ситуацию и предпочтительный способ связи — отвечу в разумный срок.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Связаться',
                'trust_chips' => [
                    ['text' => 'Адвокатский статус'],
                    ['text' => 'Челябинск'],
                    ['text' => 'Гражданские / арбитраж / уголовные'],
                    ['text' => 'Суд присяжных'],
                ],
            ], 'Форма'),
            $mk('cta', 'cta', [
                'heading' => 'Нужна взвешенная правовая позиция?',
                'body' => 'Если вам важны спокойная стратегия и понятные шаги — оставьте заявку или свяжитесь по контактам.',
                'button_text' => 'Перейти к контактам',
                'button_url' => '/contacts',
            ], 'CTA'),
        ];
    }

    private static function ensureInnerPages(int $tenantId, $now): void
    {
        $defs = [
            ['slug' => 'about', 'name' => 'Об адвокате', 'menu' => true, 'order' => 20, 'sections' => self::sectionsAbout($tenantId, $now)],
            ['slug' => 'practice-areas', 'name' => 'Практики', 'menu' => true, 'order' => 30, 'sections' => self::sectionsPracticeAreas($tenantId, $now)],
            ['slug' => 'criminal-defense', 'name' => 'Уголовная защита', 'menu' => false, 'order' => 0, 'sections' => self::sectionsCriminal($tenantId, $now)],
            ['slug' => 'jury-trial', 'name' => 'Суд присяжных', 'menu' => true, 'order' => 35, 'sections' => self::sectionsJury($tenantId, $now)],
            ['slug' => 'civil-disputes', 'name' => 'Гражданские споры', 'menu' => false, 'order' => 0, 'sections' => self::sectionsCivil($tenantId, $now)],
            ['slug' => 'arbitration', 'name' => 'Арбитраж', 'menu' => false, 'order' => 0, 'sections' => self::sectionsArbitration($tenantId, $now)],
            ['slug' => 'migration', 'name' => 'Иностранные граждане и миграция', 'menu' => false, 'order' => 0, 'sections' => self::sectionsMigration($tenantId, $now)],
            ['slug' => 'practice', 'name' => 'Подход и практика', 'menu' => false, 'order' => 0, 'sections' => self::sectionsPracticeApproach($tenantId, $now)],
            ['slug' => 'jurylab-project', 'name' => 'Проект JuryLab', 'menu' => false, 'order' => 0, 'sections' => self::sectionsJuryLab($tenantId, $now)],
            ['slug' => 'faq', 'name' => 'Вопросы и ответы', 'menu' => true, 'order' => 45, 'sections' => self::sectionsFaqPage($tenantId, $now)],
            ['slug' => 'contacts', 'name' => 'Контакты', 'menu' => true, 'order' => 50, 'sections' => self::sectionsContacts($tenantId, $now)],
            ['slug' => 'privacy-policy', 'name' => 'Политика конфиденциальности', 'menu' => false, 'order' => 0, 'sections' => self::sectionsPolicy($tenantId, $now, 'Политика конфиденциальности', self::htmlPrivacy())],
            ['slug' => 'consent-personal-data', 'name' => 'Согласие на обработку персональных данных', 'menu' => false, 'order' => 0, 'sections' => self::sectionsPolicy($tenantId, $now, 'Согласие', self::htmlConsent())],
        ];

        foreach ($defs as $def) {
            $pageId = (int) DB::table('pages')
                ->where('tenant_id', $tenantId)
                ->where('slug', $def['slug'])
                ->value('id');
            if ($pageId > 0) {
                DB::table('pages')->where('id', $pageId)->update([
                    'name' => $def['name'],
                    'show_in_main_menu' => $def['menu'],
                    'main_menu_sort_order' => $def['order'],
                    'status' => 'published',
                    'updated_at' => $now,
                ]);

                if ($def['slug'] === 'about') {
                    self::syncAboutSections($tenantId, $pageId, $now);
                }
                if ($def['slug'] === 'practice-areas') {
                    self::syncPracticeAreasSections($tenantId, $pageId, $now);
                }
                if ($def['slug'] === 'contacts') {
                    self::syncContactsSections($tenantId, $pageId, $now);
                }

                continue;
            }
            $pageId = (int) DB::table('pages')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $def['name'],
                'slug' => $def['slug'],
                'template' => 'default',
                'status' => 'published',
                'published_at' => $now,
                'show_in_main_menu' => $def['menu'],
                'main_menu_sort_order' => $def['order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($def['sections']($pageId) as $row) {
                DB::table('page_sections')->insert($row);
            }
        }
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsAbout(int $tenantId, $now): callable
    {
        return fn (int $pageId): array => self::aboutSectionsRows($tenantId, $pageId, $now);
    }

    /**
     * Страница «Об адвокате»: био с портретом, сетка направлений, текст, блок доверия, CTA.
     *
     * @return list<array<string, mixed>>
     */
    private static function aboutSectionsRows(int $tenantId, int $pageId, $now): array
    {
        $mk = self::mkSection($tenantId, $pageId, $now);
        $u = fn (string $f): string => self::brandRelativeUrl($f);

        return [
            $mk('bio', 'founder_expert_bio', [
                'heading' => 'Дементьев Никита Владимирович',
                'lead' => 'Адвокат Адвокатской палаты Челябинской области. Регистрационный номер 74/1764 в Едином государственном реестре адвокатов Российской Федерации.',
                'paragraphs' => [
                    ['text' => 'Осуществляю деятельность в Коллегии адвокатов «Защитник» Челябинской области. Работаю с гражданскими, арбитражными и уголовными делами; веду дела с участием присяжных заседателей и сопровождаю иностранных граждан на территории РФ.'],
                ],
                'portrait_image_url' => $u('portrait.jpg'),
                'portrait_image_alt' => 'Дементьев Никита Владимирович — адвокат',
                'section_id' => 'profile',
                'trust_points' => [
                    ['text' => 'Рег. № 74/1764 в реестре адвокатов РФ'],
                    ['text' => 'Практика с 2011 года'],
                    ['text' => 'Коллегия адвокатов «Защитник»'],
                ],
                'cta_label' => 'Записаться на консультацию',
                'cta_anchor' => '/contacts',
            ], 'Профиль', 0),
            $mk('specializations', 'problem_cards', [
                'section_heading' => 'Специализация',
                'section_lead' => 'Ключевые направления работы: от гражданских споров и арбитража до уголовной защиты и сопровождения иностранных граждан.',
                'footnote' => '',
                'accent_image_url' => '',
                'full_width_cards' => true,
                'items' => [
                    [
                        'title' => 'Гражданские дела',
                        'description' => 'Семейные, земельные, договорные и иные споры: претензии, иски, представительство в суде.',
                        'solution' => '',
                        'link_url' => '/civil-disputes',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                    [
                        'title' => 'Арбитраж',
                        'description' => 'Корпоративные споры, сделки, коммерческие конфликты и споры с органами. Представительство в АПК.',
                        'solution' => '',
                        'link_url' => '/arbitration',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                    [
                        'title' => 'Уголовная защита',
                        'description' => 'Защита подозреваемых и обвиняемых; интересы потерпевших; стратегия на следствии и в суде.',
                        'solution' => '',
                        'link_url' => '/criminal-defense',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                    [
                        'title' => 'Иностранные граждане',
                        'description' => 'Защита прав и миграционное сопровождение на территории РФ; процессуальная поддержка.',
                        'solution' => '',
                        'link_url' => '/migration',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                ],
            ], 'Направления', 10),
            $mk('education', 'structured_text', [
                'title' => 'Образование, присяжные и проекты',
                'content' => '<p>Прошёл специализированное обучение по защите обвиняемых по уголовному делу в суде с участием присяжных заседателей.</p>'
                    .'<p>Ежегодно повышаю квалификацию на Высших курсах, организованных Федеральной палатой адвокатов РФ, у ведущих специалистов в сфере суда присяжных, в том числе в Санкт-Петербурге и Москве.</p>'
                    .'<p>Руководитель образовательного проекта «Лаборатория суда присяжных», создатель обучающей онлайн-платформы для адвокатов <a href="https://jurylab.ru" rel="noopener noreferrer" target="_blank">JuryLab.ru</a>.</p>',
                'max_width' => 'wide',
            ], 'Развитие и проекты', 20),
            $mk('credentials', 'credentials_grid', [
                'section_heading' => 'Статус и доверие',
                'lead' => 'Прозрачные реквизиты и понятные рамки работы с первого контакта.',
                'items' => [
                    ['title' => 'Член Адвокатской палаты Челябинской области', 'description' => 'Официальный статус и регулярное повышение квалификации.'],
                    ['title' => 'Коллегия «Защитник»', 'description' => 'Профессиональная среда и соблюдение стандартов адвокатской этики.'],
                    ['title' => 'Суд присяжных', 'description' => 'Отдельная компетенция подготовки и ведения дел с участием коллегии присяжных.'],
                ],
                'background_image_url' => $u('credentials-bg.jpg'),
                'supporting_image_url' => '',
                'supporting_image_alt' => '',
            ], 'Доверие', 30),
            $mk('closing_cta', 'cta', [
                'heading' => 'Обсудите ситуацию',
                'body' => 'Кратко опишите вопрос и удобный способ связи — отвечу в разумный срок.',
                'button_text' => 'Контакты и офис',
                'button_url' => '/contacts',
                'secondary_button_text' => 'Заявка на сайте',
                'secondary_button_url' => '/#expert-inquiry',
            ], 'CTA', 40),
        ];
    }

    private static function brandRelativeUrl(string $file): string
    {
        return 'site/brand/'.ltrim($file, '/');
    }

    private static function syncAboutSections(int $tenantId, int $pageId, $now): void
    {
        DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $pageId)->delete();
        foreach (self::aboutSectionsRows($tenantId, $pageId, $now) as $row) {
            DB::table('page_sections')->insert($row);
        }
    }

    private static function syncContactsSections(int $tenantId, int $pageId, $now): void
    {
        DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $pageId)->delete();
        foreach (self::contactsSectionsRows($tenantId, $pageId, $now) as $row) {
            DB::table('page_sections')->insert($row);
        }
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsPracticeAreas(int $tenantId, $now): callable
    {
        return fn (int $pageId): array => self::practiceAreasSectionsRows($tenantId, $pageId, $now);
    }

    /**
     * Секции страницы «Практики»: intro, flagship (суд присяжных), сетка, helper, подход, CTA (builder-first).
     *
     * @return list<array<string, mixed>>
     */
    private static function practiceAreasSectionsRows(int $tenantId, int $pageId, $now): array
    {
        $mk = self::mkSection($tenantId, $pageId, $now);
        $introHtml = '<p>Моя работа охватывает несколько ключевых направлений адвокатской практики: уголовную защиту, дела с участием присяжных, гражданские споры, арбитраж и защиту прав иностранных граждан.</p>'
            .'<p>Для каждого направления подготовлена отдельная страница. Это помогает быстро сориентироваться в характере правовой ситуации и понять, какой формат работы может быть уместен именно в вашем случае.</p>';
        $helperHtml = '<p>На практике правовая ситуация не всегда укладывается в одну категорию. Один и тот же конфликт может затрагивать одновременно гражданско-правовые, уголовно-правовые или процессуальные вопросы.</p>'
            .'<p>Если вы не уверены, какую страницу открыть в первую очередь, достаточно кратко описать обстоятельства обращения — это позволит определить правовую природу ситуации и дальнейший формат работы.</p>';
        $approachHtml = '<p>Я не использую шаблонный подход к правовой помощи. Первичный вывод по делу формируется только после изучения обстоятельств, документов и процессуального положения доверителя.</p>'
            .'<p>В зависимости от задачи работа может включать консультацию, правовую оценку, подготовку документов, участие в переговорах, сопровождение на стадии следствия или представительство в суде.</p>';

        return [
            $mk('intro', 'structured_text', [
                'title' => null,
                'content' => $introHtml,
                'max_width' => 'wide',
            ], 'Вводный текст', 0),
            $mk('flagship_jury', 'founder_expert_bio', [
                'heading' => 'Отдельная компетенция — защита в суде с участием присяжных',
                'lead' => 'Защита по делам с участием присяжных требует не только глубокого знания уголовного процесса, но и специальной подготовки к судебной коммуникации, структуре позиции и работе в особом формате рассмотрения дела.',
                'paragraphs' => [
                    ['text' => 'Это направление занимает отдельное место в моей профессиональной практике и связано не только с работой по делам, но и с образовательной деятельностью в рамках проекта JuryLab и «Лаборатории суда присяжных».'],
                ],
                'portrait_image_url' => '',
                'cta_label' => 'Подробнее о практике суда присяжных',
                'cta_anchor' => '/jury-trial',
            ], 'Акцент: суд присяжных', 10),
            $mk('practice_grid', 'problem_cards', [
                'section_heading' => 'Направления практики',
                'section_lead' => '',
                'footnote' => '',
                'accent_image_url' => '',
                'full_width_cards' => true,
                'items' => [
                    [
                        'title' => 'Уголовная защита',
                        'description' => 'Защита подозреваемых и обвиняемых на стадии проверки, предварительного следствия и в суде. Представление интересов потерпевших, анализ процессуальных рисков и выстраивание правовой позиции по делу.',
                        'solution' => '',
                        'link_url' => '/criminal-defense',
                        'link_label' => 'Подробнее',
                        'is_featured' => true,
                    ],
                    [
                        'title' => 'Суд присяжных',
                        'description' => 'Отдельное направление практики, требующее специальной подготовки, другой логики защиты и особого подхода к судебному процессу. Одно из ключевых экспертных направлений сайта.',
                        'solution' => '',
                        'link_url' => '/jury-trial',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                    [
                        'title' => 'Гражданские споры',
                        'description' => 'Семейные, земельные, договорные и иные гражданско-правовые споры. Подготовка процессуальных документов, правовая позиция, сопровождение переговоров и представительство в суде.',
                        'solution' => '',
                        'link_url' => '/civil-disputes',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                    [
                        'title' => 'Арбитраж',
                        'description' => 'Корпоративные споры, споры по сделкам, коммерческие конфликты и споры с государственными органами. Представление интересов бизнеса в арбитражном процессе.',
                        'solution' => '',
                        'link_url' => '/arbitration',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                    [
                        'title' => 'Иностранные граждане и миграция',
                        'description' => 'Защита прав иностранных граждан на территории Российской Федерации, вопросы миграционного законодательства и сопровождение в правовых ситуациях, требующих точной процессуальной работы.',
                        'solution' => '',
                        'link_url' => '/migration',
                        'link_label' => 'Подробнее',
                        'is_featured' => false,
                    ],
                ],
            ], 'Сетка направлений', 20),
            $mk('helper_uncertain', 'notice_box', [
                'title' => 'Если ваша ситуация не укладывается в одно название',
                'text' => $helperHtml,
                'tone' => 'neutral',
            ], 'Подсказка посетителю', 30),
            $mk('approach', 'structured_text', [
                'title' => 'Каждое дело требует отдельного анализа',
                'content' => $approachHtml,
                'max_width' => 'wide',
            ], 'Подход к работе', 40),
            $mk('closing_cta', 'cta', [
                'heading' => 'Не уверены, с чего начать?',
                'body' => 'Если вам нужна правовая оценка ситуации, можно начать с первичного обращения. После уточнения обстоятельств будет понятен профиль практики и возможный формат дальнейшей работы.',
                'button_text' => 'Связаться',
                'button_url' => '/#expert-inquiry',
                'secondary_button_text' => 'Перейти в контакты',
                'secondary_button_url' => '/contacts',
            ], 'Завершение страницы', 50),
        ];
    }

    /**
     * Идемпотентно пересобирает страницу «Практики» (bootstrap / migrate).
     */
    private static function syncPracticeAreasSections(int $tenantId, int $pageId, $now): void
    {
        DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $pageId)->delete();
        foreach (self::practiceAreasSectionsRows($tenantId, $pageId, $now) as $row) {
            DB::table('page_sections')->insert($row);
        }
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsCriminal(int $tenantId, $now): callable
    {
        $html = '<p>Уголовная защита: сопровождение на стадии проверки сообщения о преступлении, досудебного производства и судебного разбирательства.</p>'
            .'<p>Фокус — на оценке обвинения, допустимости доказательств, избрании и изменении меры пресечения, тактике допросов и иных следственных действий, а также на стратегии в суде.</p>'
            .'<p>Каждое дело уникально: без гарантий результата, с опорой на факты и процессуальное право.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsJury(int $tenantId, $now): callable
    {
        $html = '<p>Дела с участием присяжных заседателей требуют отдельной подготовки: отбор коллегии, структура речи, работа с доказательствами и ожиданиями «обычных граждан» в составе суда.</p>'
            .'<p>Экспертиза подкрепляется образовательной и методической линией — проектом «Лаборатория суда присяжных» и JuryLab. Публичный маркетинговый переход на внешний домен не используется до отдельной договорённости.</p>'
            .'<p><a href="/contacts">Связаться</a> или <a href="/#expert-inquiry">оставить заявку</a> для первичной оценки ситуации.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsCivil(int $tenantId, $now): callable
    {
        $html = '<p>Гражданские споры: семейные, земельные, жилищные, договорные и иные категории. Претензионный этап, подготовка иска или возражений, представительство в суде.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsArbitration(int $tenantId, $now): callable
    {
        $html = '<p>Арбитражные споры: корпоративные конфликты, сделки, взыскание, взаимодействие с контрагентами и госорганами в рамках АПК.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsMigration(int $tenantId, $now): callable
    {
        $html = '<p>Сопровождение иностранных граждан: миграционный учёт, статус, конфликтные ситуации с органами и третьими лицами, процессуальная поддержка.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsPracticeApproach(int $tenantId, $now): callable
    {
        $html = '<p>На этой странице — подход к работе с судебной практикой и позициями по категориям дел. Конкретные результаты по делам без вашего разрешения не публикуются.</p>'
            .'<p>Если у вас есть обезличенный запрос на разбор типовой ситуации — укажите это в <a href="/contacts">форме связи</a>.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsJuryLab(int $tenantId, $now): callable
    {
        $html = '<p>JuryLab и «Лаборатория суда присяжных» — образовательное и методическое направление, связанное с профессиональной практикой в делах присяжных.</p>'
            .'<p>Цель — систематизировать знания и повышать качество подготовки к процессу с участием коллегии присяжных. Это не замена консультации и не публичная «продажа» внешнего продукта.</p>';

        return fn (int $pageId): array => self::mainStructuredPage($tenantId, $pageId, $now, $html);
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsFaqPage(int $tenantId, $now): callable
    {
        return function (int $pageId) use ($tenantId, $now): array {
            $mk = self::mkSection($tenantId, $pageId, $now);

            return [
                $mk('main', 'rich_text', ['content' => '<p>Краткие ответы на типовые вопросы до записи на консультацию. Подробности всегда зависят от обстоятельств дела.</p>'], 'Вводный текст', 0),
                $mk('page_faq', 'content_faq', [
                    'title' => 'Частые вопросы',
                    'items' => [
                        ['question' => 'Когда обращаться к адвокату?', 'answer' => '<p>Как только появляется угроза правам или процессуальным срокам: досудебные стадии часто критичны для тактики.</p>'],
                        ['question' => 'Что взять на консультацию?', 'answer' => '<p>Документы по делу, переписку, постановления и контакты. Если чего-то нет — разберём, что запросить.</p>'],
                        ['question' => 'Можно ли подключиться в ходе дела?', 'answer' => '<p>Да, в большинстве категорий возможна смена защитника или подключение второго. Ограничения зависят от стадии и процессуального статуса.</p>'],
                        ['question' => 'Работаете ли по арбитражу и миграции?', 'answer' => '<p>Да, это отдельные направления практики; см. страницы раздела.</p>'],
                        ['question' => 'Как проходит первый контакт?', 'answer' => '<p>Короткий созвон или переписка, затем согласование формата консультации и перечня документов.</p>'],
                        ['question' => 'Как определяется стоимость?', 'answer' => '<p>Зависит от сложности, срочности и объёма работ; обсуждается после понимания задачи, без навязанных пакетов.</p>'],
                    ],
                ], 'FAQ', 10),
            ];
        };
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsContacts(int $tenantId, $now): callable
    {
        return fn (int $pageId): array => self::contactsSectionsRows($tenantId, $pageId, $now);
    }

    /**
     * Страница «Контакты»: builder-first — intro, premium contacts_info, форма, процесс, финальный CTA.
     *
     * @return list<array<string, mixed>>
     */
    private static function contactsSectionsRows(int $tenantId, int $pageId, $now): array
    {
        $mk = self::mkSection($tenantId, $pageId, $now);
        $lon = self::OFFICE_MAP_LON;
        $lat = self::OFFICE_MAP_LAT;
        $mapUrl = 'https://yandex.ru/map-widget/v1/?ll='.$lon.'%2C'.$lat.'&z=18&pt='.$lon.'%2C'.$lat.'%2Cpm2rdm';

        $introHtml = '<p class="text-pretty">Для консультации и записи на встречу свяжитесь удобным способом. Очный приём в Челябинске — по предварительной договорённости.</p>';

        $rightCoreHtml = '<p>Кратко можно описать ситуацию в форме ниже — этого достаточно, чтобы договориться о следующем шаге.</p>';

        $channelRows = [
            [
                'type' => 'phone',
                'value' => '+7 (965) 853-44-83',
                'is_enabled' => true,
                'is_primary' => true,
                'sort_order' => 0,
                'label' => null,
                'url' => null,
                'is_override_url' => false,
                'note' => null,
                'cta_label' => null,
                'open_in_new_tab' => null,
            ],
            [
                'type' => 'email',
                'value' => 'adv174ur@gmail.com',
                'is_enabled' => true,
                'is_primary' => false,
                'sort_order' => 10,
                'label' => null,
                'url' => null,
                'is_override_url' => false,
                'note' => null,
                'cta_label' => null,
                'open_in_new_tab' => null,
            ],
            [
                'type' => 'vk',
                'value' => 'https://vk.com/sudjury74',
                'is_enabled' => true,
                'is_primary' => false,
                'sort_order' => 20,
                'label' => null,
                'url' => null,
                'is_override_url' => false,
                'note' => null,
                'cta_label' => null,
                'open_in_new_tab' => null,
            ],
        ];

        return [
            $mk('main', 'rich_text', [
                'heading' => '',
                'content' => $introHtml,
            ], 'Вводный текст', 0),
            $mk('contacts_block', 'contacts_info', [
                'title' => 'Контактные данные',
                'description' => 'Телефон, электронная почта и адрес офиса.',
                'phone' => '+7 (965) 853-44-83',
                'email' => 'adv174ur@gmail.com',
                'address' => self::OFFICE_ADDRESS_LINE,
                'working_hours' => '',
                'map_enabled' => true,
                'map_provider' => 'yandex',
                'map_public_url' => $mapUrl,
                // Карта + кнопка: button_only скрывает iframe (только ссылка).
                'map_display_mode' => 'embed_and_button',
                'map_title' => '',
                'additional_note' => 'Личный приём и очные встречи — по предварительной договорённости.',
                'channels' => $channelRows,
            ], 'Контактные данные', 10),
            $mk('contact_core_right', 'structured_text', [
                'title' => null,
                'content' => $rightCoreHtml,
                'max_width' => 'full',
            ], 'Сценарии и карта', 15),
            $mk('expert_lead_form', 'expert_lead_form', [
                'heading' => 'Описать ситуацию',
                'subheading' => 'Кратко изложите вопрос и укажите удобный способ ответа. Не нужно готовить «идеальное» письмо — достаточно сути и контакта, чтобы согласовать дальнейший формат общения.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Форма',
                'trust_chips' => [],
            ], 'Форма', 20),
            $mk('contacts_reassurance', 'structured_text', [
                'title' => null,
                'content' => '<p>Ответ по существу, без автоматических рассылок. Если ситуация не терпит задержки, надёжнее сразу <a href="tel:+79658534483">позвонить</a> — так быстрее согласовать следующий шаг.</p>',
                'max_width' => 'full',
            ], 'Резюме перед футером', 30),
            $mk('contacts_closing', 'cta', [
                'heading' => 'Ещё варианты связи',
                'body' => 'Можно оставить заявку выше или выбрать привычный канал — телефон, почта или форма.',
                'button_text' => 'Позвонить',
                'button_url' => 'tel:+79658534483',
                'secondary_button_text' => 'Перейти к форме',
                'secondary_button_url' => '#expert-inquiry',
            ], 'Финальный блок', 40),
        ];
    }

    /**
     * @return callable(int): list<array<string, mixed>>
     */
    private static function sectionsPolicy(int $tenantId, $now, string $title, string $html): callable
    {
        return function (int $pageId) use ($tenantId, $now, $title, $html): array {
            $mk = self::mkSection($tenantId, $pageId, $now);

            return [$mk('main', 'rich_text', ['content' => $html], $title, 0)];
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mainStructuredPage(int $tenantId, int $pageId, $now, string $html): array
    {
        return [
            self::mkSection($tenantId, $pageId, $now)('main', 'rich_text', ['content' => $html], 'Основной текст', 0),
        ];
    }

    /**
     * @return \Closure(string, string, array, ?string, int): array<string, mixed>
     */
    private static function mkSection(int $tenantId, int $pageId, $now): \Closure
    {
        return function (string $key, string $type, array $data, ?string $title, int $sort) use ($tenantId, $pageId, $now): array {
            return [
                'tenant_id' => $tenantId,
                'page_id' => $pageId,
                'section_key' => $key,
                'section_type' => $type,
                'title' => $title,
                'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'sort_order' => $sort,
                'is_visible' => true,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };
    }

    private static function htmlPrivacy(): string
    {
        return '<p>Настоящая политика описывает базовые принципы обработки персональных данных на сайте. Подробности фиксируются в соглашениях при оказании услуг.</p>';
    }

    private static function htmlConsent(): string
    {
        return '<p>Текст согласия на обработку персональных данных размещается для ознакомления. Актуальная версия может предоставляться отдельно при консультации.</p>';
    }

    private static function seedFaqsIfEmpty(int $tenantId, $now): void
    {
        if (DB::table('faqs')->where('tenant_id', $tenantId)->exists()) {
            return;
        }
        $qs = [
            ['Когда лучше обратиться к адвокату?', 'Как только возникает риск для прав или приближается процессуальный срок — чем раньше, тем шире выбор тактики.'],
            ['Что подготовить к консультации?', 'Имеющиеся документы, переписку и краткую хронологию. Если чего-то нет — подскажу, что запросить.'],
            ['Можно ли подключиться к уже начатому делу?', 'Да, в большинстве случаев это возможно; нюансы зависят от стадии и процессуального статуса.'],
            ['Ведёте ли вы дела в арбитраже и по миграции?', 'Да, это отдельные направления; см. страницы практики.'],
            ['Как происходит первый контакт?', 'Короткий звонок или сообщение, затем согласование формата консультации.'],
            ['Как обсуждается вознаграждение?', 'После понимания объёма работ и срочности; без скрытых условий в публичном тексте.'],
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

    private static function ensureFormConfig(int $tenantId, $now): void
    {
        $fields = [
            'goal_text' => ['label' => 'Суть ситуации и вопрос', 'required' => true],
            'preferred_schedule' => ['label' => 'Удобное время для ответа', 'required' => false],
            'district' => ['label' => 'Направление практики (если знаете)', 'required' => false],
            'comment' => ['label' => 'Дополнительно', 'required' => false],
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
                'title' => 'Запрос адвокату',
                'description' => 'Консультация и первичный контакт',
                'is_enabled' => true,
                'recipient_email' => 'adv174ur@gmail.com',
                'success_message' => 'Спасибо! Сообщение получено. Я свяжусь с вами для уточнения деталей.',
                'error_message' => 'Не удалось отправить сообщение. Попробуйте позже или позвоните.',
                'fields_json' => $fieldsJson,
                'settings_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        // Повторный bootstrap: убираем устаревшие поля из шаблона автошколы из fields_json.
        DB::table('form_configs')->where('id', $existingId)->update([
            'title' => 'Запрос адвокату',
            'description' => 'Консультация и первичный контакт',
            'fields_json' => $fieldsJson,
            'updated_at' => $now,
        ]);
    }

    private static function seedSeoRecords(int $tenantId, $now): void
    {
        $homeId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homeId <= 0) {
            return;
        }

        $graph = [
            [
                '@type' => 'Attorney',
                'name' => 'Дементьев Никита Владимирович',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Челябинск',
                    'streetAddress' => 'ул. Братьев Кашириных, д. 85 «А», оф. 1',
                    'postalCode' => '464016',
                    'addressCountry' => 'RU',
                ],
                'areaServed' => 'Челябинск',
            ],
            [
                '@type' => 'WebSite',
                'name' => 'Дементьев Никита Владимирович — адвокат',
                'url' => url('/'),
            ],
        ];

        SeoMeta::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'seoable_type' => Page::class,
                'seoable_id' => $homeId,
            ],
            [
                'meta_title' => 'Адвокат Дементьев Никита Владимирович — Челябинск | гражданские, арбитраж, уголовные дела',
                'meta_description' => 'Адвокат АП Челябинской области, рег. № 74/1764. Гражданские, арбитражные и уголовные дела, суд присяжных, иностранные граждане. Консультация в Челябинске.',
                'meta_keywords' => 'адвокат Челябинск, адвокат Дементьев, суд присяжных, уголовный адвокат, арбитражный адвокат',
                'h1' => 'Адвокат Дементьев Никита Владимирович',
                'canonical_url' => null,
                'robots' => null,
                'og_title' => 'Адвокат Дементьев Н. В. — Челябинск',
                'og_description' => 'Персональный сайт адвоката: гражданские, арбитражные и уголовные дела; суд присяжных.',
                'og_image' => null,
                'og_type' => 'website',
                'twitter_card' => 'summary_large_image',
                'is_indexable' => true,
                'is_followable' => true,
                'json_ld' => $graph,
            ],
        );

        $pages = DB::table('pages')->where('tenant_id', $tenantId)->where('slug', '!=', 'home')->get();
        foreach ($pages as $p) {
            $slug = (string) $p->slug;
            $name = (string) $p->name;
            $title = $name.' — адвокат Дементьев | Челябинск';
            $desc = match ($slug) {
                'jury-trial' => 'Суд присяжных в Челябинске: подготовка и ведение дел с участием коллегии присяжных.',
                'contacts' => 'Контакты адвоката в Челябинске: телефон, email, ВКонтакте, адрес офиса и форма обращения. Очные встречи по предварительной договорённости.',
                'faq' => 'Ответы на частые вопросы до консультации: сроки, документы, подключение к делу, направления практики, первый контакт и порядок обсуждения вознаграждения.',
                'practice-areas' => 'Направления практики адвоката Дементьева Никиты Владимировича: уголовные дела, суд присяжных, гражданские споры, арбитраж и защита прав иностранных граждан в Челябинске.',
                default => $name.' — адвокатская практика в Челябинске.',
            };
            if ($slug === 'practice-areas') {
                $title = 'Практики адвоката в Челябинске — уголовная защита, суд присяжных, гражданские споры и арбитраж';
            }
            $h1 = $slug === 'practice-areas' ? 'Практики' : $name;
            $ogTitle = $slug === 'practice-areas' ? 'Практики адвоката Дементьева — Челябинск' : $title;
            $ogDesc = $slug === 'practice-areas'
                ? 'Уголовная защита, суд присяжных, гражданские и арбитражные споры, защита прав иностранных граждан. Ориентир по направлениям и формату работы.'
                : $desc;
            SeoMeta::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'seoable_type' => Page::class,
                    'seoable_id' => (int) $p->id,
                ],
                [
                    'meta_title' => $title,
                    'meta_description' => $desc,
                    'meta_keywords' => null,
                    'h1' => $h1,
                    'canonical_url' => null,
                    'robots' => null,
                    'og_title' => $ogTitle,
                    'og_description' => $ogDesc,
                    'og_image' => null,
                    'og_type' => 'article',
                    'twitter_card' => 'summary',
                    'is_indexable' => true,
                    'is_followable' => true,
                    'json_ld' => [],
                ],
            );
        }
    }
}
