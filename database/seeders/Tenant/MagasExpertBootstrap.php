<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Http\Controllers\HomeController;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantFooterLink;
use App\Models\TenantFooterSection;
use App\Models\TenantSetting;
use App\Tenant\Footer\FooterSectionType;
use App\Tenant\Footer\TenantFooterLinkKind;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageArea;
use App\Tenant\ExpertPr\MagasHeroDefaults;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent bootstrap for tenant Sergei Magas (expert PR / Web3 narrative).
 * Создание недостающих строк (не «refresh» уже существующего контента). Повторный прогон с новыми текстами секций может не заменять данные без отдельного upsert.
 * Если тенант создался не с выбранным canonical id (из-за AUTO_INCREMENT), см. {@see \App\Console\Commands\TenantMagasReassignCanonicalIdCommand}.
 *
 * Manual bootstrap: {@see \App\Console\Commands\TenantMagasBootstrapCommand}.
 * **Production primary host** для этого тенанта: `sergeymagas.com` (без фиксации по индексу массива).
 * **Не задавать** канонический id на прод без осознанного `--canonical-id` / controlled slot.
 *
 * Canonical EN spelling: **Sergei Magas** (see TZ). Not registered in {@see \Database\Seeders\DatabaseSeeder}.
 */
final class MagasExpertBootstrap
{
    public const SLUG = 'sergey-magas';

    /**
     * Рекомендуемый id для controlled local seed / reassign tooling; **новая вставка по умолчанию** — обычный AUTO_INCREMENT.
     * Укажите {@see self::run()} вторым аргументом или CLI `--canonical-id=…`, если нужен конкретный слот.
     */
    public const CANONICAL_TENANT_ID = 5;

    /** Production apex; всегда `is_primary` в `tenant_domains` для этого хоста. */
    private const PRODUCTION_PRIMARY_HOST = 'sergeymagas.com';

    /**
     * Портрет в hero кадрируется с фокусом справа (субъект), типографика слева работает поверх однотонной зоны.
     * Источник: официальный URL с текущего лендинга эксперта.
     */
    private const HERO_PORTRAIT_URL = 'https://sergeymagas.ru/magas.png';

    /** Файл в репозитории TZ — кладётся в {@code tenants/{id}/public/site/brand/favicon.ico}. */
    private const SOURCE_FAVICON_ICO_REL = 'docs/tenants_tz/magas/Prod-eng/favicon.ico';

    /** Портрет hero: локальный файл в TZ перекрывает HTTP; сохраняется как {@code site/brand/magas-hero.png|.jpg}. */
    private const SOURCE_HERO_MAGAS_REL = 'docs/tenants_tz/magas/Prod-eng/magas.png';

    public const BRAND = 'Sergei Magas';

    private static bool $publishBootstrap = false;

    /** With {@see self::$publishBootstrap}: also publish legal/terms, service detail scaffolds and FAQ; default is draft/legal placeholder. */
    private static bool $allowPlaceholderPublish = false;

    /** When applying page status updates: force draft snapshot (used with or without publish). */
    private static bool $forceDraftBootstrap = false;

    private static ?int $requestedCanonicalTenantId = null;

    /**
     * @param  bool  $publishBootstrap  без флагов: новые страницы в draft, индекс запрещён в SEO; существующие published не трогаем
     * @param  int|null  $canonicalTenantId  только при осознанном выборе слота; null = обычная вставка без фиксированного id
     * @param  bool  $allowPlaceholderPublish  вместе с publish: опубликовать scaffold (legal, services/*, FAQ)
     * @param  bool  $forceDraft  при обновлении существующего тенанта принудительно выставить статусы из bootstrap (draft если нет publish)
     */
    public static function run(
        bool $publishBootstrap = false,
        ?int $canonicalTenantId = null,
        bool $allowPlaceholderPublish = false,
        bool $forceDraft = false,
    ): void {
        self::$publishBootstrap = $publishBootstrap;
        self::$requestedCanonicalTenantId = $canonicalTenantId;
        self::$allowPlaceholderPublish = $allowPlaceholderPublish;
        self::$forceDraftBootstrap = $forceDraft;
        try {
            $tid = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
            if ($tid <= 0) {
                DB::transaction(static function (): void {
                    self::createFullTenant();
                });
            } else {
                DB::transaction(static function () use ($tid): void {
                    self::ensureContent($tid);
                });
            }
            $tid = (int) DB::table('tenants')->where('slug', self::SLUG)->value('id');
            if ($tid > 0) {
                HomeController::forgetCachedPayloadForTenant($tid);
            }
        } finally {
            self::$publishBootstrap = false;
            self::$requestedCanonicalTenantId = null;
            self::$allowPlaceholderPublish = false;
            self::$forceDraftBootstrap = false;
        }
    }

    private static function canonicalTenantInsertSlot(): ?int
    {
        return self::$requestedCanonicalTenantId;
    }

    private static function isPlaceholderScaffoldSlug(string $slug): bool
    {
        return $slug === 'terms'
            || $slug === 'cases'
            || str_starts_with($slug, 'services/');
    }

    /**
     * @return array{0: string, 1: \Illuminate\Support\Carbon|null}
     */
    private static function resolvedPagePublication(string $slug, $now): array
    {
        if (! self::$publishBootstrap) {
            return ['draft', null];
        }
        if ($slug === 'privacy') {
            return ['published', $now];
        }
        $want = self::$allowPlaceholderPublish || ! self::isPlaceholderScaffoldSlug($slug);

        return $want ? ['published', $now] : ['draft', null];
    }

    private static function seoShouldIndexPage(string $slug): bool
    {
        if (! self::$publishBootstrap) {
            return false;
        }

        /** Юридические и заготовки кейсов не индексируем из bootstrap — финальный текст задаётся вручную в Filament. */
        if (in_array($slug, ['privacy', 'terms', 'cases'], true)) {
            return false;
        }

        return self::$allowPlaceholderPublish || ! self::isPlaceholderScaffoldSlug($slug);
    }

    private static function createFullTenant(): void
    {
        $planId = (int) (DB::table('plans')->value('id') ?? 0);
        $ownerId = (int) (DB::table('users')->value('id') ?? 0);
        $now = now();

        $row = [
            'name' => self::BRAND,
            'slug' => self::SLUG,
            'brand_name' => self::BRAND,
            'theme_key' => 'expert_pr',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'country' => null,
            'plan_id' => $planId > 0 ? $planId : null,
            'owner_user_id' => $ownerId > 0 ? $ownerId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $slotId = self::canonicalTenantInsertSlot();
        if ($slotId !== null && ! DB::table('tenants')->where('id', $slotId)->exists()) {
            DB::table('tenants')->insert(array_merge(['id' => $slotId], $row));
            $tenantId = $slotId;
        } else {
            $tenantId = (int) DB::table('tenants')->insertGetId($row);
        }

        self::insertDomains($tenantId);
        self::applySettings($tenantId);
        self::syncMagasBrandFaviconFromDocs($tenantId);
        self::applyMagasTenantFaviconFromStorage($tenantId);
        self::syncMagasHeroPortraitIntoTenantStorage($tenantId);
        $homeId = self::insertPage($tenantId, 'home', 'Home', false, 0, $now);
        self::insertHomeSections($tenantId, $homeId, $now);
        self::ensureHomeFaqSection($tenantId, $homeId, $now);
        self::removeExpertLeadFormFromHome($tenantId, $homeId);
        self::insertInnerPages($tenantId, $now);
        self::ensureFaqs($tenantId, $now);
        self::seedFormConfig($tenantId, $now);
        self::seedHomeSeo($tenantId, $homeId, $now);
        self::seedInnerPagesSeo($tenantId, $now);
        self::ensureQuota($tenantId);
        self::ensureMagasHomeExpertHeroPortrait($tenantId);
        self::ensureMagasHomeMidCtaStrip($tenantId);
        self::ensureMagasTypedFooterBaseline($tenantId);
    }

    private static function ensureContent(int $tenantId): void
    {
        $now = now();
        self::insertDomains($tenantId);
        self::applySettings($tenantId);
        self::syncMagasBrandFaviconFromDocs($tenantId);
        self::applyMagasTenantFaviconFromStorage($tenantId);
        self::syncMagasHeroPortraitIntoTenantStorage($tenantId);
        $homeId = self::ensurePage($tenantId, 'home', 'Home', false, 0, $now);
        if (DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $homeId)->doesntExist()) {
            self::insertHomeSections($tenantId, $homeId, $now);
        }
        self::ensureHomeFaqSection($tenantId, $homeId, $now);
        self::removeExpertLeadFormFromHome($tenantId, $homeId);
        self::insertInnerPages($tenantId, $now);
        self::ensureFaqs($tenantId, $now);
        if (DB::table('form_configs')->where('tenant_id', $tenantId)->where('form_key', 'expert_lead')->doesntExist()) {
            self::seedFormConfig($tenantId, $now);
        }
        self::seedHomeSeo($tenantId, $homeId, $now);
        self::seedInnerPagesSeo($tenantId, $now);
        self::ensureQuota($tenantId);
        self::ensureMagasHomeExpertHeroPortrait($tenantId);
        self::ensureMagasHomeMidCtaStrip($tenantId);
        self::ensureMagasTypedFooterBaseline($tenantId);
    }

    /**
     * Mid-page CTA между «How we work» и био — для существующих инсталляций без повторной вставки всего home.
     */
    private static function ensureMagasHomeMidCtaStrip(int $tenantId): void
    {
        $homeId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homeId <= 0) {
            return;
        }
        if (DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $homeId)->where('section_key', 'home_mid_cta')->exists()) {
            return;
        }
        $processSo = (int) (DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $homeId)->where('section_key', 'process_steps')->value('sort_order') ?? 0);
        $founderSo = (int) (DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $homeId)->where('section_key', 'founder_expert_bio')->value('sort_order') ?? 0);
        $sort = ($processSo > 0 && $founderSo > $processSo)
            ? (int) floor(($processSo + $founderSo) / 2)
            : ($founderSo > 0 ? $founderSo - 5 : 55);
        $now = now();
        DB::table('page_sections')->insert([
            'tenant_id' => $tenantId,
            'page_id' => $homeId,
            'section_key' => 'home_mid_cta',
            'section_type' => 'enrollment_cta_strip',
            'title' => null,
            'data_json' => json_encode(self::homeMidCtaData(), JSON_UNESCAPED_UNICODE),
            'sort_order' => $sort,
            'is_visible' => true,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Типизированный подвал Filament-compatible: синхронизируются только секции с префиксом {@code magas_footer_*}
     * (группы ссылок по IA, блок «ответов», нижняя полоса). Другие включённые секции подвала на тенанте сохраняются.
     *
     * Секция {@code magas_footer_link_groups_v1} считается **bootstrap-owned**: при каждом прогоне команды ссылки
     * в ней пересоздаются — ручные правки этой секции в админке следующим bootstrap будут потеряны.
     *
     * @see docs/tenants/magas/v1-brand-domains-and-ops.md
     */
    private static function ensureMagasTypedFooterBaseline(int $tenantId): void
    {
        if ($tenantId <= 0 || ! Schema::hasTable('tenant_footer_sections') || ! Schema::hasTable('tenant_footer_links')) {
            return;
        }
        $slug = (string) DB::table('tenants')->where('id', $tenantId)->value('slug');
        if ($slug !== self::SLUG) {
            return;
        }

        TenantFooterSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'section_key' => 'magas_footer_contacts_v1'],
            [
                'type' => FooterSectionType::CONTACTS,
                'title' => null,
                'body' => null,
                'meta_json' => [
                    'headline' => 'Contact',
                    'description' => '',
                    'show_phone' => true,
                    'show_telegram' => true,
                    'show_whatsapp' => false,
                    'show_email' => true,
                    'show_address' => false,
                ],
                'sort_order' => 10,
                'is_enabled' => true,
            ],
        );

        $linksSection = TenantFooterSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'section_key' => 'magas_footer_link_groups_v1'],
            [
                'type' => FooterSectionType::LINK_GROUPS,
                'title' => null,
                'body' => null,
                'meta_json' => [
                    'headline' => '',
                    'group_titles' => [
                        'explore' => 'Explore',
                        'company' => 'Company',
                        'legal' => 'Legal',
                    ],
                ],
                'sort_order' => 20,
                'is_enabled' => true,
            ],
        );

        TenantFooterSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'section_key' => 'magas_footer_response_v1'],
            [
                'type' => FooterSectionType::CONDITIONS_LIST,
                'title' => null,
                'body' => null,
                'meta_json' => [
                    'headline' => 'Response & follow-up',
                    'items' => [
                        'Inbound replies within one business day for qualified B2B enquiries.',
                        'If the situation is time-sensitive reputational pressure, mention it in your brief—we respond pragmatically.',
                    ],
                ],
                'sort_order' => 45,
                'is_enabled' => true,
            ],
        );

        TenantFooterSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'section_key' => 'magas_footer_bottom_v1'],
            [
                'type' => FooterSectionType::BOTTOM_BAR,
                'title' => null,
                'body' => null,
                'meta_json' => [
                    'copyright_text' => '',
                    'secondary_text' => '',
                ],
                'sort_order' => 90,
                'is_enabled' => true,
            ],
        );

        TenantFooterLink::query()->where('section_id', $linksSection->id)->delete();

        /** @var list<array{group_key: string, label: string, url: string, sort_order: int}> $nav */
        $nav = [
            ['group_key' => 'explore', 'label' => 'Services overview', 'url' => '/services', 'sort_order' => 10],
            ['group_key' => 'explore', 'label' => 'Cases', 'url' => '/cases', 'sort_order' => 20],
            ['group_key' => 'explore', 'label' => 'Media outreach program', 'url' => '/services/media-outreach', 'sort_order' => 30],
            ['group_key' => 'company', 'label' => 'About', 'url' => '/about', 'sort_order' => 10],
            ['group_key' => 'company', 'label' => 'FAQ', 'url' => '/faq', 'sort_order' => 20],
            ['group_key' => 'company', 'label' => 'Contact brief', 'url' => '/contacts#expert-inquiry', 'sort_order' => 30],
            ['group_key' => 'legal', 'label' => 'Contacts', 'url' => '/contacts', 'sort_order' => 10],
            ['group_key' => 'legal', 'label' => 'Privacy', 'url' => '/privacy', 'sort_order' => 20],
            ['group_key' => 'legal', 'label' => 'Terms', 'url' => '/terms', 'sort_order' => 30],
        ];

        foreach ($nav as $row) {
            TenantFooterLink::query()->create([
                'section_id' => $linksSection->id,
                'group_key' => $row['group_key'],
                'label' => $row['label'],
                'url' => $row['url'],
                'link_kind' => TenantFooterLinkKind::Internal,
                'target' => '_self',
                'sort_order' => $row['sort_order'],
                'is_enabled' => true,
            ]);
        }
    }

    private static function ensureQuota(int $tenantId): void
    {
        $t = Tenant::query()->find($tenantId);
        if ($t !== null) {
            app(TenantStorageQuotaService::class)->ensureQuotaRecord($t);
        }
    }

    /**
     * Копирует {@see self::SOURCE_FAVICON_ICO_REL} в {@code tenants/{id}/public/site/brand/favicon.ico}.
     */
    private static function syncMagasBrandFaviconFromDocs(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $path = base_path(str_replace('\\', DIRECTORY_SEPARATOR, self::SOURCE_FAVICON_ICO_REL));
        if (! is_file($path)) {
            return;
        }
        TenantStorage::forTrusted($tenantId)->putInArea(TenantStorageArea::PublicSite, 'brand/favicon.ico', File::get($path));
    }

    private static function applyMagasTenantFaviconFromStorage(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $ts = TenantStorage::forTrusted($tenantId);
        $rel = 'site/brand/favicon.ico';
        if (! $ts->existsPublic($rel)) {
            return;
        }
        TenantSetting::setForTenant($tenantId, 'branding.favicon_path', $ts->publicPath($rel), 'string');
        TenantSetting::setForTenant($tenantId, 'branding.favicon', $ts->publicUrl($rel), 'string');
    }

    /**
     * Кладём портрет в public storage: файл из TZ или (один раз) HTTP из канонических URL.
     * Без активного интернета / файла TZ в БД остаётся {@see self::HERO_PORTRAIT_URL}.
     */
    private static function syncMagasHeroPortraitIntoTenantStorage(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $storage = TenantStorage::forTrusted($tenantId);
        $localHero = base_path(str_replace('\\', DIRECTORY_SEPARATOR, self::SOURCE_HERO_MAGAS_REL));
        if (is_file($localHero)) {
            $storage->putInArea(TenantStorageArea::PublicSite, 'brand/magas-hero.png', File::get($localHero));

            return;
        }

        if (app()->environment('testing')) {
            return;
        }
        foreach (['site/brand/magas-hero.png', 'site/brand/magas-hero.jpg'] as $have) {
            if ($storage->existsPublic($have)) {
                return;
            }
        }

        foreach ([self::HERO_PORTRAIT_URL, 'https://sergeymagas.com/magas.png'] as $url) {
            try {
                $r = Http::timeout(22)->retry(2, 800)->connectTimeout(8)->get($url);
                if (! $r->successful()) {
                    continue;
                }
                $body = $r->body();
                if (strlen($body) < 1024) {
                    continue;
                }
                $isPng = str_starts_with($body, "\x89PNG");
                $isJpg = str_starts_with($body, "\xFF\xD8\xFF");
                if (! $isPng && ! $isJpg) {
                    continue;
                }
                $name = $isPng ? 'magas-hero.png' : 'magas-hero.jpg';
                $storage->putInArea(TenantStorageArea::PublicSite, 'brand/'.$name, $body);

                break;
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * Единый контракт секции {@code enrollment_cta_strip} между первичной вставкой главной и {@see self::ensureMagasHomeMidCtaStrip()}.
     *
     * @return array<string, mixed>
     */
    private static function homeMidCtaData(): array
    {
        return [
            'enabled' => true,
            'section_id' => 'home-mid-cta',
            'heading' => 'Have a milestone coming up?',
            'lead' => 'Send a short brief — we\'ll reply with a pragmatic PR sequence.',
            'button_label' => 'Send a brief',
            'source_context' => 'home_mid_cta',
            'goal_prefill' => 'We have a milestone coming up and want a pragmatic PR sequence.',
        ];
    }

    /**
     * Подтягивает в JSON hero главной недостающие URL/alt/focal — **без перезаписи** уже заданных в админке значений.
     * Повторный bootstrap таким образом не откатывает ручную смену херо-ассета.
     *
     * @see MagasHeroDefaults::mergePortraitDefaultsOnlyMissing()
     */
    private static function ensureMagasHomeExpertHeroPortrait(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $homeId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homeId <= 0) {
            return;
        }
        $section = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $homeId)
            ->where('section_key', 'expert_hero')
            ->first();
        if ($section === null) {
            return;
        }

        $data = json_decode((string) ($section->data_json ?? ''), true);
        $data = is_array($data) ? $data : [];

        $merged = app(MagasHeroDefaults::class)->mergePortraitDefaultsOnlyMissing($tenantId, $data);

        $sameUrl = trim((string) ($data['hero_image_url'] ?? '')) === trim((string) ($merged['hero_image_url'] ?? ''));
        $sameAlt = trim((string) ($data['hero_image_alt'] ?? '')) === trim((string) ($merged['hero_image_alt'] ?? ''));
        $encodeStable = static function (?array $a): string {
            if ($a === null || $a === []) {
                return '';
            }
            $j = json_encode($a, JSON_UNESCAPED_UNICODE);

            return is_string($j) ? $j : '';
        };

        $bgPrev = isset($data['hero_background_presentation']) && is_array($data['hero_background_presentation'])
            ? $encodeStable($data['hero_background_presentation']) : '';
        $bgNext = isset($merged['hero_background_presentation']) && is_array($merged['hero_background_presentation'])
            ? $encodeStable($merged['hero_background_presentation']) : '';
        if ($sameUrl && $sameAlt && $bgPrev === $bgNext) {
            return;
        }

        DB::table('page_sections')->where('id', (int) $section->id)->update([
            'data_json' => json_encode($merged, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function candidateHosts(): array
    {
        $hosts = [self::PRODUCTION_PRIMARY_HOST];

        if (app()->isLocal()) {
            /** OSPanel / project.ini обычно использует apex без дефиса; старые алиасы оставляем для совместимости. */
            $hosts[] = 'sergeymagas.rentbase.local';
            $hosts[] = 'sergeymagas.local';
            $hosts[] = 'sergey-magas.rentbase.local';
            $hosts[] = 'sergey-magas.local';
            $dh = config('app.tenant_default_host');
            if (is_string($dh) && $dh !== ''
                && $dh !== self::PRODUCTION_PRIMARY_HOST && ! in_array($dh, $hosts, true)) {
                $hosts[] = $dh;
            }
        }

        /** @var list<string> $out */
        $out = array_values(array_unique(array_filter($hosts)));

        return $out;
    }

    /**
     * Перед любыми апдейтами is_primary: конфликт «чужой tenant» → исключение без частичного состояния.
     *
     * @param  list<string>  $hosts
     */
    private static function assertCandidateHostsAssignable(int $tenantId, array $hosts): void
    {
        foreach ($hosts as $host) {
            if ($host === '') {
                continue;
            }
            $existing = DB::table('tenant_domains')->where('host', $host)->first();
            if ($existing === null) {
                continue;
            }
            if ((int) $existing->tenant_id !== $tenantId) {
                throw new \RuntimeException(
                    "Bootstrap Magas: host «{$host}» уже привязан к другому клиенту (tenant_id {$existing->tenant_id}). Освободите домен или не запускайте bootstrap на этом окружении."
                );
            }
        }
    }

    private static function mergeSslDesiredWithExisting(string $currentSsl, string $desiredSsl): string
    {
        if (in_array($currentSsl, [
            TenantDomain::SSL_ISSUED,
            TenantDomain::SSL_NOT_REQUIRED,
        ], true)) {
            return $currentSsl;
        }

        return $desiredSsl;
    }

    private static function insertDomains(int $tenantId): void
    {
        $hosts = self::candidateHosts();
        self::assertCandidateHostsAssignable($tenantId, $hosts);

        DB::table('tenant_domains')->where('tenant_id', $tenantId)->update([
            'is_primary' => false,
            'updated_at' => now(),
        ]);

        $isLocalEnv = app()->isLocal();

        foreach ($hosts as $host) {
            if ($host === '') {
                continue;
            }
            $existing = DB::table('tenant_domains')->where('host', $host)->first();
            $isPrimary = $host === self::PRODUCTION_PRIMARY_HOST;

            $type = $host === self::PRODUCTION_PRIMARY_HOST
                ? TenantDomain::TYPE_CUSTOM
                : TenantDomain::TYPE_SUBDOMAIN;
            $sslDesired = ($host === self::PRODUCTION_PRIMARY_HOST && ! $isLocalEnv)
                ? TenantDomain::SSL_PENDING
                : TenantDomain::SSL_NOT_REQUIRED;

            if ($existing !== null) {
                $currentSsl = (string) ($existing->ssl_status ?? '');
                $ssl = self::mergeSslDesiredWithExisting($currentSsl, $sslDesired);
                DB::table('tenant_domains')->where('id', $existing->id)->update([
                    'is_primary' => $isPrimary,
                    'type' => $type,
                    'ssl_status' => $ssl,
                    'status' => 'active',
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('tenant_domains')->insert([
                'tenant_id' => $tenantId,
                'host' => $host,
                'type' => $type,
                'is_primary' => $isPrimary,
                'status' => 'active',
                'ssl_status' => $sslDesired,
                'verified_at' => now(),
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private static function tenantSettingKeyMissing(int $tenantId, string $dotKey): bool
    {
        $parts = explode('.', $dotKey, 2);
        $group = $parts[0] ?? 'general';
        $k = $parts[1] ?? $parts[0];

        return ! TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('group', $group)
            ->where('key', $k)
            ->exists();
    }

    private static function setSettingIfMissing(int $tenantId, string $key, mixed $value, string $type): void
    {
        if (! self::tenantSettingKeyMissing($tenantId, $key)) {
            return;
        }
        TenantSetting::setForTenant($tenantId, $key, $value, $type);
    }

    private static function applySettings(int $tenantId): void
    {
        self::setSettingIfMissing($tenantId, 'general.site_name', self::BRAND.' — B2B PR & narrative for Web3', 'string');
        self::setSettingIfMissing(
            $tenantId,
            'general.short_description',
            'Strategic media relations, narrative and crisis-ready communications for teams building in Web3 and emerging tech.',
            'string',
        );
        self::setSettingIfMissing($tenantId, 'general.domain', 'https://sergeymagas.com', 'string');
        self::setSettingIfMissing($tenantId, 'branding.primary_color', '#c9a068', 'string');
        /**
         * Резерв для публичного hero, если в секции Hero на главной ещё нет {@code hero_image_url}:
         * читается в {@see tenant_branding_hero_url()} (редактируется в кабинете, не в Blade).
         */
        self::setSettingIfMissing($tenantId, 'branding.hero', self::HERO_PORTRAIT_URL, 'string');
        self::setSettingIfMissing($tenantId, 'contacts.email', 'hello@sergeymagas.com', 'string');
        self::setSettingIfMissing($tenantId, 'contacts.telegram', 'sergeimagas', 'string');
        self::setSettingIfMissing($tenantId, 'contacts.phone', '', 'string');
        self::setSettingIfMissing(
            $tenantId,
            'general.footer_tagline',
            'B2B communications: media relations, narrative, and crisis-ready messaging.',
            'string',
        );
    }

    private static function ensurePage(int $tenantId, string $slug, string $name, bool $menu, int $order, $now): int
    {
        $id = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
        if ($id > 0) {
            $existingRow = DB::table('pages')->where('id', $id)->first();
            $existingStatus = is_object($existingRow) ? (string) ($existingRow->status ?? 'draft') : 'draft';
            [$stDefault, $pubAtDefault] = self::resolvedPagePublication($slug, $now);

            $update = [
                'show_in_main_menu' => $menu,
                'main_menu_sort_order' => $order,
                'updated_at' => $now,
            ];

            if (self::$forceDraftBootstrap) {
                $update['status'] = 'draft';
                $update['published_at'] = null;
            } elseif (self::$publishBootstrap) {
                $wouldDemotePublishedPlaceholder = $existingStatus === 'published'
                    && self::isPlaceholderScaffoldSlug($slug)
                    && ! self::$allowPlaceholderPublish;

                if (! $wouldDemotePublishedPlaceholder) {
                    $update['status'] = $stDefault;
                    $update['published_at'] = $pubAtDefault;
                }
            }

            DB::table('pages')->where('id', $id)->update($update);

            return $id;
        }

        return self::insertPage($tenantId, $slug, $name, $menu, $order, $now);
    }

    private static function insertPage(int $tenantId, string $slug, string $name, bool $menu, int $order, $now): int
    {
        [$status, $publishedAt] = self::resolvedPagePublication($slug, $now);

        return (int) DB::table('pages')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'template' => 'default',
            'status' => $status,
            'published_at' => $publishedAt,
            'show_in_main_menu' => $menu,
            'main_menu_sort_order' => $order,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeSeoPayload(): array
    {
        $indexable = self::seoShouldIndexPage('home');
        $graph = [
            [
                '@type' => 'Person',
                'name' => self::BRAND,
                'jobTitle' => 'Independent PR & communications advisor (Web3 / emerging tech)',
                'description' => 'B2B PR, media outreach, narrative strategy, crisis communications and thought leadership for teams that need clarity and credibility with global audiences.',
                'sameAs' => [
                    'https://t.me/sergeimagas',
                    'https://www.linkedin.com/in/sergeimagas',
                ],
            ],
        ];

        return [
            'meta_title' => self::BRAND.' — B2B PR, media & narrative for Web3 teams',
            'meta_description' => 'Conversion-focused PR partner: media outreach, narrative, reputation and crisis-ready communications. English-first site; brief form + direct channels.',
            'h1' => self::BRAND,
            'is_indexable' => $indexable,
            'is_followable' => $indexable,
            'og_title' => self::BRAND.' — B2B PR for Web3',
            'og_description' => 'Strategic communications that turn technical depth into trust: coverage, narrative, and calm execution under pressure.',
            'json_ld' => $graph,
        ];
    }

    /**
     * @param  array<string, mixed>  $fullUpsertPayload
     */
    private static function upsertPageSeoRespectingManualEdits(int $tenantId, int $pageId, array $fullUpsertPayload, $now): void
    {
        if ($pageId <= 0) {
            return;
        }

        $criteria = [
            'tenant_id' => $tenantId,
            'seoable_type' => Page::class,
            'seoable_id' => $pageId,
        ];
        /** @var SeoMeta|null $existing */
        $existing = SeoMeta::withoutGlobalScope('tenant')->where($criteria)->first();

        $indexable = (bool) ($fullUpsertPayload['is_indexable'] ?? false);
        $followable = (bool) ($fullUpsertPayload['is_followable'] ?? $indexable);

        if ($existing !== null && self::$forceDraftBootstrap) {
            $existing->update([
                'is_indexable' => false,
                'is_followable' => false,
                'updated_at' => $now,
            ]);

            return;
        }

        if ($existing !== null) {
            $existing->update([
                'is_indexable' => $indexable,
                'is_followable' => $followable,
                'updated_at' => $now,
            ]);

            return;
        }

        $fullUpsertPayload['updated_at'] = $now;

        SeoMeta::withoutGlobalScope('tenant')->updateOrCreate($criteria, $fullUpsertPayload);
    }

    private static function seedHomeSeo(int $tenantId, int $homePageId, $now): void
    {
        if ($homePageId <= 0) {
            return;
        }
        $payload = self::homeSeoPayload();
        $graph = $payload['json_ld'];
        unset($payload['json_ld']);

        self::upsertPageSeoRespectingManualEdits($tenantId, $homePageId, array_merge($payload, ['json_ld' => $graph]), $now);
    }

    private static function seedInnerPagesSeo(int $tenantId, $now): void
    {
        foreach (self::innerPageSeoRowTemplates() as $slug => $row) {
            $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            $jd = [];
            if (isset($row['json_ld_builder']) && \is_callable($row['json_ld_builder'])) {
                $jd = ($row['json_ld_builder'])();
            }
            unset($row['json_ld_builder'], $row['json_ld']);
            $indexable = self::seoShouldIndexPage($slug);
            $payload = array_merge($row, [
                'is_indexable' => $indexable,
                'is_followable' => $indexable,
                'json_ld' => $jd,
                'updated_at' => $now,
            ]);

            self::upsertPageSeoRespectingManualEdits($tenantId, $pageId, $payload, $now);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function innerPageSeoRowTemplates(): array
    {
        $personRef = [
            '@type' => 'Person',
            'name' => self::BRAND,
        ];

        $mkService = static function (string $name, string $desc) use ($personRef): array {
            return [
                [
                    '@type' => 'Service',
                    'name' => $name,
                    'description' => $desc,
                    'provider' => $personRef,
                ],
            ];
        };

        return [
            'services' => [
                'meta_title' => 'PR & communications services — '.self::BRAND,
                'meta_description' => 'Media outreach, PR strategy, reputation, crisis communications and thought leadership — structured landing pages for each lane.',
                'h1' => 'Services',
                'og_title' => 'Services — '.self::BRAND,
                'og_description' => 'Choose a communications lane; each URL is optimized for discovery and brief intake.',
                'json_ld_builder' => static fn (): array => [
                    [
                        '@type' => 'WebPage',
                        'name' => 'Services — '.self::BRAND,
                    ],
                ],
            ],
            'services/media-outreach' => [
                'meta_title' => 'Media outreach — '.self::BRAND,
                'meta_description' => 'Reporter-friendly packaging, milestone sequencing and disciplined pitching for technical B2B and Web3 teams.',
                'h1' => 'Media outreach',
                'og_title' => 'Media outreach — '.self::BRAND,
                'og_description' => 'Earned outreach that respects news cycles — without gimmick stacking.',
                'json_ld_builder' => static function () use ($mkService): array {
                    return $mkService(
                        'Media outreach',
                        'Packaging milestones for reporters; sequencing that respects news cycles.',
                    );
                },
            ],
            'services/pr-strategy' => [
                'meta_title' => 'PR strategy & narrative — '.self::BRAND,
                'meta_description' => 'Narrative spine, proof assets, channel mix and cadence aligned to your milestones.',
                'h1' => 'PR strategy',
                'og_title' => 'PR strategy — '.self::BRAND,
                'og_description' => 'Coherent storyline across pitch, owned channels and media.',
                'json_ld_builder' => static function () use ($mkService): array {
                    return $mkService(
                        'PR strategy',
                        'Narrative architecture, proof points, milestones and owned channels.',
                    );
                },
            ],
            'services/reputation-management' => [
                'meta_title' => 'Reputation management — '.self::BRAND,
                'meta_description' => 'Monitoring, escalation paths and counter-lines that hold under scrutiny.',
                'h1' => 'Reputation management',
                'og_title' => 'Reputation management — '.self::BRAND,
                'og_description' => 'Calm response patterns when narrative pressure arrives.',
                'json_ld_builder' => static function () use ($mkService): array {
                    return $mkService(
                        'Reputation management',
                        'Ongoing monitoring, counter-narrative and calm response patterns.',
                    );
                },
            ],
            'services/crisis-communications' => [
                'meta_title' => 'Crisis communications — '.self::BRAND,
                'meta_description' => 'Fact-first drafts, stakeholder maps, pacing and redundancy for high-stakes moments.',
                'h1' => 'Crisis communications',
                'og_title' => 'Crisis communications — '.self::BRAND,
                'og_description' => 'Playbooks and approvals that keep tone disciplined under heat.',
                'json_ld_builder' => static function () use ($mkService): array {
                    return $mkService(
                        'Crisis communications',
                        'Playbooks, approvals, facts-first statements and stakeholder maps.',
                    );
                },
            ],
            'services/thought-leadership' => [
                'meta_title' => 'Thought leadership — '.self::BRAND,
                'meta_description' => 'Bylines, long-form, talks and proof assets that compound authority.',
                'h1' => 'Thought leadership',
                'og_title' => 'Thought leadership — '.self::BRAND,
                'og_description' => 'Long-form leverage for analysts and global readers.',
                'json_ld_builder' => static function () use ($mkService): array {
                    return $mkService(
                        'Thought leadership',
                        'Bylines, long-form, talks and proof assets that compound authority.',
                    );
                },
            ],
            'cases' => [
                'meta_title' => 'Cases & outcomes — '.self::BRAND,
                'meta_description' => 'Illustrative outcome framing when NDAs bind specifics — swap for attributable proof when cleared.',
                'h1' => 'Cases',
                'og_title' => 'Cases — '.self::BRAND,
                'og_description' => 'How proof is articulated under confidentiality constraints.',
            ],
            'about' => [
                'meta_title' => 'About — '.self::BRAND,
                'meta_description' => 'Operator-led communications for founders and protocol teams building in Web3 and deep tech.',
                'h1' => 'About',
                'og_title' => 'About — '.self::BRAND,
                'og_description' => 'Background, posture and engagement model.',
            ],
            'contacts' => [
                'meta_title' => 'Contacts — '.self::BRAND,
                'meta_description' => 'Email, Telegram and the project brief — remote-first engagement by appointment.',
                'h1' => 'Contacts',
                'og_title' => 'Contacts — '.self::BRAND,
                'og_description' => 'Reach out with milestone context via the channels you prefer.',
            ],
            'privacy' => [
                'meta_title' => 'Privacy policy — '.self::BRAND,
                'meta_description' => 'How contact details from forms may be processed; replace with counsel-approved copy before launch.',
                'h1' => 'Privacy',
                'og_title' => 'Privacy — draft',
                'og_description' => 'Draft placeholder pending legal review.',
            ],
            'terms' => [
                'meta_title' => 'Terms of use — '.self::BRAND,
                'meta_description' => 'Limitation of liability and acceptable use; replace with counsel-approved copy before launch.',
                'h1' => 'Terms',
                'og_title' => 'Terms — draft',
                'og_description' => 'Draft placeholder pending legal review.',
            ],
        ];
    }

    /**
     * Данные секции FAQ на home (единый источник для первичной вставки и ensure).
     *
     * @return array<string, mixed>
     */
    private static function faqHomeSectionPresentationData(): array
    {
        return [
            'section_heading' => 'FAQ preview',
            'source' => 'faqs_table',
        ];
    }

    private static function ensureHomeFaqSection(int $tenantId, int $homePageId, $now): void
    {
        if ($homePageId <= 0) {
            return;
        }

        $shouldShow = self::$publishBootstrap && self::$allowPlaceholderPublish;

        $row = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $homePageId)
            ->where('section_key', 'faq')
            ->first();

        $dataJson = json_encode(self::faqHomeSectionPresentationData(), JSON_UNESCAPED_UNICODE);

        if ($row === null) {
            if (! $shouldShow) {
                return;
            }

            $maxSo = (int) (DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $homePageId)
                ->max('sort_order') ?? 0);

            DB::table('page_sections')->insert([
                'tenant_id' => $tenantId,
                'page_id' => $homePageId,
                'section_key' => 'faq',
                'section_type' => 'faq',
                'title' => 'FAQ',
                'data_json' => $dataJson,
                'sort_order' => $maxSo > 0 ? $maxSo + 10 : 50,
                'is_visible' => true,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('page_sections')->where('id', $row->id)->update([
            'is_visible' => $shouldShow,
            'status' => $shouldShow ? 'published' : 'draft',
            'data_json' => $dataJson,
            'updated_at' => $now,
        ]);
    }

    /** У главной только контентные секции — форму оставляем на /contacts (как у других эксперт-тенантов). */
    private static function removeExpertLeadFormFromHome(int $tenantId, int $homePageId): void
    {
        if ($homePageId <= 0) {
            return;
        }

        DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $homePageId)
            ->where(function ($q): void {
                $q->where('section_key', 'expert_lead_form')
                    ->orWhere('section_type', 'expert_lead_form');
            })
            ->delete();
    }

    private static function insertHomeSections(int $tenantId, int $pageId, $now): void
    {
        $o = 0;
        $mk = static function (string $key, string $type, array $data, ?string $title = null) use (&$o, $tenantId, $pageId, $now): array {
            return [
                'tenant_id' => $tenantId,
                'page_id' => $pageId,
                'section_key' => $key,
                'section_type' => $type,
                'title' => $title,
                'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'sort_order' => ($o += 10),
                'is_visible' => true,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $sections = [
            $mk('expert_hero', 'expert_hero', array_merge([
                'hero_eyebrow' => 'B2B PR & narrative · Web3 & emerging tech',
                'heading' => 'Build trust before the market moves.',
                'subheading' => 'B2B PR and narrative for Web3 founders, protocol teams and deep-tech builders who need disciplined media outreach, coherent positioning, and credible proof — without losing speed.',
                'description' => '',
                'primary_cta_label' => 'Send a brief',
                'primary_cta_anchor' => '/contacts#expert-inquiry',
                'secondary_cta_label' => 'View services',
                'secondary_cta_anchor' => '/services',
                'trust_badges' => [
                    ['text' => 'Media & narrative'],
                    ['text' => 'Web3-native context'],
                    ['text' => 'Crisis-ready tone'],
                    ['text' => 'Founder-led execution'],
                ],
                'overlay_dark' => true,
                'video_trigger_label' => '',
                'hero_image_slot' => null,
                'hero_video_url' => '',
                'hero_video_poster_url' => '',
            ], app(MagasHeroDefaults::class)->portraitPresentationPayload($tenantId)), 'Hero'),
            $mk('problem_cards', 'problem_cards', [
                'section_heading' => 'Where teams feel the pressure first',
                'section_lead' => 'You are shipping product, managing community pressure, and answering investors — all at once. The site should help the right partners say “yes” faster.',
                'footnote' => 'This is not a one-page business card — it is structured for conversion and SEO.',
                'accent_image_url' => '',
                'items' => [
                    ['title' => 'Fragmented story', 'description' => 'Technical depth does not automatically read as trust to global media.', 'solution' => 'A single narrative spine across site, pitch and outreach.', 'is_featured' => true],
                    ['title' => 'Patchy coverage', 'description' => 'Random posts do not compound into reputation.', 'solution' => 'Prioritized media roadmap tied to milestones.', 'is_featured' => false],
                    ['title' => 'Crisis ambiguity', 'description' => 'When heat arrives, wording matters as much as facts.', 'solution' => 'Clear escalation copy and disciplined channels.', 'is_featured' => false],
                    ['title' => 'Thin proof', 'description' => 'Claims need receipts your audience recognises.', 'solution' => 'Case framing and attributable outcomes — without fluff.', 'is_featured' => false],
                    ['title' => 'Time cost', 'description' => 'Founders cannot run press alone.', 'solution' => 'Brief-first workflow aligned to Telegram and CRM.', 'is_featured' => false],
                ],
            ]),
            $mk('services_teaser', 'cards_teaser', [
                'heading' => 'Core services',
                'description' => 'Pick a lane; each page expands the deliverables and what “good” looks like.',
                'card_button_variant' => 'text_link',
                'cards' => [
                    ['title' => 'Media outreach', 'text' => 'Targets, sequencing, pitching discipline and reporter-friendly packaging.', 'image' => null, 'button_text' => 'Explore media outreach', 'button_url' => '/services/media-outreach'],
                    ['title' => 'PR strategy', 'text' => 'Narrative architecture, proof points, milestones and owned channels.', 'image' => null, 'button_text' => 'Shape the narrative', 'button_url' => '/services/pr-strategy'],
                    ['title' => 'Reputation', 'text' => 'Ongoing monitoring, counter-narrative and calm response patterns.', 'image' => null, 'button_text' => 'Protect reputation', 'button_url' => '/services/reputation-management'],
                    ['title' => 'Crisis communications', 'text' => 'Playbooks, approvals, facts-first statements and stakeholder maps.', 'image' => null, 'button_text' => 'Prepare crisis response', 'button_url' => '/services/crisis-communications'],
                    ['title' => 'Thought leadership', 'text' => 'Bylines, long-form, talks and proof assets that compound authority.', 'image' => null, 'button_text' => 'Build authority', 'button_url' => '/services/thought-leadership'],
                    ['title' => 'All services', 'text' => 'IA overview — ideal if you want the map before drilling down.', 'image' => null, 'button_text' => 'View all services', 'button_url' => '/services'],
                ],
            ], 'Services preview'),
            $mk('process_steps', 'process_steps', [
                'section_heading' => 'How we work',
                'aside_image_url' => '',
                'aside_video_url' => '',
                'aside_video_poster_url' => '',
                'aside_title' => 'Fast intake, disciplined delivery',
                'aside_body' => 'You receive a pragmatic plan aligned to milestones — not theatrical decks that ignore capacity.',
                'steps' => [
                    ['title' => 'Brief & fit', 'body' => 'Goals, timelines, milestones, sensitivities — captured in CRM with full context.'],
                    ['title' => 'Angle & storyline', 'body' => 'We align proof, tone and spokesperson map before outreach starts.'],
                    ['title' => 'Execution sprint', 'body' => 'Sequenced media work, iterative assets, measurable checkpoints.'],
                    ['title' => 'Learn & tighten', 'body' => 'What moved, what did not — folded into next sprint or launch.'],
                ],
            ]),
            $mk('home_mid_cta', 'enrollment_cta_strip', self::homeMidCtaData()),
            $mk('founder_expert_bio', 'founder_expert_bio', [
                'heading' => 'Operator-led communications',
                'lead' => 'I work hands-on with founders and core teams — not as a detached agency façade. Expect direct language, pragmatic sequencing, and media behaviour that survives scrutiny.',
                'paragraphs' => [
                    ['text' => 'Background spans high-stakes narrative work with global audiences: translating technical differentiation into credible storylines reporters can use.'],
                    ['text' => 'Operating model is intentionally lean: curated partner network when volume demands it — without losing coherence.'],
                    ['text' => 'If you came from a minimalist one-pager — the tone carries forward; the structure expands for SEO and funnel clarity.'],
                ],
                'photo_slot' => null,
                'section_id' => 'about',
                'portrait_image_url' => '',
                'portrait_image_alt' => self::BRAND,
                'trust_points' => [
                    ['text' => 'Web3-native context'],
                    ['text' => 'Crisis instinct without panic copy'],
                    ['text' => 'Brief-first workflows'],
                    ['text' => 'English-first outbound'],
                ],
                'cta_label' => 'Discuss a roadmap',
                'cta_anchor' => '/contacts#expert-inquiry',
                'cta_goal_prefill' => 'I want a concise PR roadmap for the next milestone.',
                'cta_repeat_after_trust' => true,
            ], 'Credibility'),
        ];
        if (self::$publishBootstrap && self::$allowPlaceholderPublish) {
            $sections[] = $mk('faq', 'faq', self::faqHomeSectionPresentationData());
        }

        foreach ($sections as $row) {
            DB::table('page_sections')->insert($row);
        }
    }

    private static function insertInnerPages(int $tenantId, $now): void
    {
        $defs = [
            ['slug' => 'services', 'name' => 'Services', 'order' => 10, 'sections' => 'servicesPageSections'],
            ['slug' => 'cases', 'name' => 'Cases', 'order' => 20, 'sections' => 'casesPageSections'],
            ['slug' => 'about', 'name' => 'About', 'order' => 30, 'sections' => 'aboutPageSections'],
            ['slug' => 'contacts', 'name' => 'Contacts', 'order' => 40, 'sections' => 'contactsPageSections'],
            ['slug' => 'privacy', 'name' => 'Privacy', 'order' => 0, 'sections' => 'legalPrivacySections'],
            ['slug' => 'terms', 'name' => 'Terms', 'order' => 0, 'sections' => 'legalTermsSections'],
        ];

        foreach ($defs as $def) {
            $slug = $def['slug'];
            $menu = ! in_array($slug, ['privacy', 'terms'], true);
            $pid = self::ensurePage($tenantId, $slug, $def['name'], $menu, $def['order'], $now);
            if (DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $pid)->exists()) {
                continue;
            }
            $method = $def['sections'];
            $rows = match ($method) {
                'servicesPageSections' => self::servicesPageSections($tenantId, $pid, $now),
                'casesPageSections' => self::casesPageSections($tenantId, $pid, $now),
                'aboutPageSections' => self::aboutPageSections($tenantId, $pid, $now),
                'contactsPageSections' => self::contactsPageSections($tenantId, $pid, $now),
                'legalPrivacySections' => self::legalPrivacySections($tenantId, $pid, $now),
                'legalTermsSections' => self::legalTermsSections($tenantId, $pid, $now),
                default => [],
            };
            foreach ($rows as $row) {
                DB::table('page_sections')->insert($row);
            }
        }

        $serviceSlugs = [
            'services/media-outreach' => 'Media outreach',
            'services/pr-strategy' => 'PR strategy',
            'services/reputation-management' => 'Reputation management',
            'services/crisis-communications' => 'Crisis communications',
            'services/thought-leadership' => 'Thought leadership',
        ];
        foreach ($serviceSlugs as $slug => $title) {
            $pid = self::ensurePage($tenantId, $slug, $title, false, 0, $now);
            if (DB::table('page_sections')->where('tenant_id', $tenantId)->where('page_id', $pid)->exists()) {
                continue;
            }
            foreach (self::serviceDetailSections($tenantId, $pid, $title, $now) as $row) {
                DB::table('page_sections')->insert($row);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function servicesPageSections(int $tenantId, int $pageId, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);

        return [
            $mk('svc_top_strip', 'enrollment_cta_strip', [
                'enabled' => true,
                'section_id' => 'svc-top',
                'heading' => 'Choose a lane — drill into deliverables',
                'lead' => 'Each URL is optimised for discovery; start here if you prefer the overview first.',
                'button_label' => 'Skip to brief',
                'source_context' => 'services_hub_strip',
                'goal_prefill' => 'Reviewing services hub — want a prioritized PR plan.',
            ]),
            $mk('svc_grid', 'cards_teaser', [
                'heading' => 'PR & communications lanes',
                'description' => 'Deep pages track intent separately for SEO — no gimmick landing pages stacked on JS.',
                'cards' => [
                    ['title' => 'Media outreach', 'text' => 'Packaging milestones for reporters; sequencing that respects news cycles.', 'image' => null, 'button_text' => 'Details', 'button_url' => '/services/media-outreach'],
                    ['title' => 'PR strategy', 'text' => 'Narrative spine, proof assets, channel mix and cadence.', 'image' => null, 'button_text' => 'Details', 'button_url' => '/services/pr-strategy'],
                    ['title' => 'Reputation management', 'text' => 'Monitoring, escalation paths, counter-lines that hold under scrutiny.', 'image' => null, 'button_text' => 'Details', 'button_url' => '/services/reputation-management'],
                    ['title' => 'Crisis communications', 'text' => 'Fact-first drafts, stakeholder map, pacing and redundancy.', 'image' => null, 'button_text' => 'Details', 'button_url' => '/services/crisis-communications'],
                    ['title' => 'Thought leadership', 'text' => 'Long-form leverage: bylines, talks, attributable proof loops.', 'image' => null, 'button_text' => 'Details', 'button_url' => '/services/thought-leadership'],
                ],
            ]),
            $mk('svc_form', 'expert_lead_form', [
                'heading' => 'Tell us what launches next',
                'subheading' => 'We prioritize briefs tied to milestones (mainnet, raises, restructuring, geopolitical overlays).',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Brief',
                'trust_chips' => [
                    ['text' => 'Telegram-friendly'],
                    ['text' => 'CRM-aligned'],
                    ['text' => 'No fluff intake'],
                ],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function casesPageSections(int $tenantId, int $pageId, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);

        return [
            $mk('cases_intro', 'rich_text', [
                'heading' => 'Representative outcomes',
                'content' => '<p>V1 publishes <strong>illustrative</strong> situations — not fake logos — to show how we articulate proof when NDAs bind specifics. Swap for attributable wins as clearance allows.</p>',
            ]),
            $mk('cases_cards', 'cards_teaser', [
                'heading' => 'Outcomes blueprint',
                'description' => 'Replace with attributable proof when approvals land.',
                'cards' => [
                    ['title' => 'Liquidity milestone', 'text' => 'Controlled narrative tied to timelines; reporter-friendly package with verifiable artefacts.', 'image' => null, 'button_text' => '', 'button_url' => ''],
                    ['title' => 'Reputation reset', 'text' => 'Escalation cadence across social + wire + selective long-form.', 'image' => null, 'button_text' => '', 'button_url' => ''],
                    ['title' => 'Category creation', 'text' => 'Bridge technical differentiation to comparative framing analysts reuse.', 'image' => null, 'button_text' => '', 'button_url' => ''],
                ],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function aboutPageSections(int $tenantId, int $pageId, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);

        return [
            $mk('about_bio', 'founder_expert_bio', [
                'heading' => 'About '.self::BRAND,
                'lead' => 'Independent advisor at the intersection of technical depth and global readability — so your milestones land as credible news, not noise.',
                'paragraphs' => [
                    ['text' => 'Engagements are selective: high-trust teams building systems that need translation, not hype.'],
                    ['text' => 'You will see Telegram and LinkedIn surfaced alongside the brief form — speed should not break process.'],
                ],
                'photo_slot' => null,
                'section_id' => '',
                'portrait_image_url' => '',
                'portrait_image_alt' => self::BRAND,
                'trust_points' => [
                    ['text' => 'B2B / Web3 context'],
                    ['text' => 'Crisis-ready'],
                    ['text' => 'English-first'],
                ],
                'cta_label' => 'Open brief',
                'cta_anchor' => '/contacts#expert-inquiry',
                'cta_goal_prefill' => 'About page — want scoped PR options.',
                'cta_repeat_after_trust' => false,
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function contactsPageSections(int $tenantId, int $pageId, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);

        return [
            $mk('contacts_block', 'contacts', [
                'heading' => 'Contacts',
                'phone' => '',
                'email' => 'hello@sergeymagas.com',
                'telegram' => 'sergeimagas',
                'vk_url' => '',
                'address' => 'Remote-first; meetings by appointment (EU / US-friendly timezones).',
                'social_note' => 'Prefer Telegram or LinkedIn? Use the same handles across briefs.',
            ]),
            $mk('contacts_form', 'expert_lead_form', [
                'heading' => 'Project brief',
                'subheading' => 'Industry, timeline and budget cues help prioritise seriousness without turning the form into a novel.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Brief',
                'trust_chips' => [
                    ['text' => 'Mapped fields'],
                    ['text' => 'Consent'],
                    ['text' => 'Spam guard'],
                ],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function legalPrivacySections(int $tenantId, int $pageId, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);

        return [
            $mk('privacy_rt', 'rich_text', [
                'heading' => 'Privacy policy (draft placeholder)',
                'content' => '<p>Replace this with counsel-approved English copy before production launch. Mention cookies and analytics explicitly if you ship GA/GTM/Telegram Pixel.</p><p>Forms record contact details entered by visitors and store them according to RentBase CRM policies for client tenants.</p>',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function legalTermsSections(int $tenantId, int $pageId, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);

        return [
            $mk('terms_rt', 'rich_text', [
                'heading' => 'Terms of use (draft placeholder)',
                'content' => '<p>This placeholder clarifies limitation of liability and acceptable use until your counsel ships final terms aligned to contracting entity and geography.</p>',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function serviceDetailSections(int $tenantId, int $pageId, string $title, $now): array
    {
        $order = 0;
        $mk = self::mkFactory($tenantId, $pageId, $now, $order);
        $body = '<p>Detailed deliverables, exclusions and proof patterns for <strong>'.htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong> belong here.</p>'
            .'<p>Edit in Filament; keep headings explicit for readability and GEO/AI discovery.</p>';

        return [
            $mk('svc_problems', 'rich_text', [
                'heading' => 'Where teams feel friction first',
                'content' => '<p>Positioning drifts, fragmented proof, or coverage that does not compound — map the pain before the deliverable list. Replace with account-specific angles when you finalize copy.</p>'
                    .'<p>Keep H2/H3 explicit for readers and for assistive/AI summarization.</p>',
            ], 'Problems'),
            $mk('svc_body', 'rich_text', [
                'heading' => $title,
                'content' => $body,
            ]),
            $mk('svc_cta_strip', 'enrollment_cta_strip', [
                'enabled' => true,
                'section_id' => 'svc-cta',
                'heading' => 'Discuss scope for '.$title,
                'lead' => 'Send a concise brief — we reply with sequencing options.',
                'button_label' => 'Open brief form',
                'source_context' => 'service_detail_strip',
                'goal_prefill' => 'Interested in '.$title.' — need scope + timeline.',
            ]),
            $mk('svc_form', 'expert_lead_form', [
                'heading' => 'Brief intake',
                'subheading' => 'Share milestone context; we prioritize realistic sequencing.',
                'form_key' => 'expert_lead',
                'section_id' => 'expert-inquiry',
                'sticky_cta_label' => 'Brief',
                'trust_chips' => [],
            ]),
        ];
    }

    /**
     * @return \Closure(string, string, array, ?string=): array<string, mixed>
     */
    private static function mkFactory(int $tenantId, int $pageId, $now, int &$order): \Closure
    {
        return static function (string $key, string $type, array $data, ?string $title = null) use ($tenantId, $pageId, $now, &$order): array {
            return [
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
        };
    }

    /**
     * Bootstrap-owned FAQ вопросы (для синхронизации статуса без перезаписи ответов из Filament).
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function faqBootstrapRows(): array
    {
        return [
            ['How fast do you respond to briefs?', 'Typically within one business day for qualified B2B/Web3 inquiries; crisis-adjacent topics are triaged immediately when flagged.'],
            ['Do you work under NDA before we share technical detail?', 'Yes — we align on scope, sensitivity and spokesperson policy before materials circulate.'],
            ['What do you need in a good brief?', 'Milestone date, audience, constraints, evidence you can show, and what success looks like in plain language.'],
            ['International vs. US‑centric media?', 'We plan outlets by geography and outlet tier; language and proof assets follow from that map.'],
            ['Do you replace an in‑house communicator?', 'We complement core teams — strategy plus execution bursts without forcing a hollow “agency façade”.'],
            ['What analytics will you insist on?', 'Only what aligns to your privacy stance — preferably first‑party lead signals and attributable coverage.'],
        ];
    }

    private static function ensureFaqs(int $tenantId, $now): void
    {
        $rows = self::faqBootstrapRows();
        $questions = array_map(static fn (array $r): string => $r[0], $rows);

        $n = (int) (DB::table('faqs')->where('tenant_id', $tenantId)->max('sort_order') ?? 0);

        foreach ($rows as [$q, $a]) {
            $exists = DB::table('faqs')->where('tenant_id', $tenantId)->where('question', $q)->exists();
            if ($exists) {
                continue;
            }
            DB::table('faqs')->insert([
                'tenant_id' => $tenantId,
                'question' => $q,
                'answer' => $a,
                'category' => null,
                'sort_order' => ($n += 10),
                'status' => 'draft',
                'show_on_home' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $shouldPublish = self::$publishBootstrap && self::$allowPlaceholderPublish;

        if (self::$forceDraftBootstrap) {
            DB::table('faqs')
                ->where('tenant_id', $tenantId)
                ->whereIn('question', $questions)
                ->update([
                    'status' => 'draft',
                    'show_on_home' => false,
                    'updated_at' => $now,
                ]);

            return;
        }

        if ($shouldPublish) {
            DB::table('faqs')
                ->where('tenant_id', $tenantId)
                ->whereIn('question', $questions)
                ->update([
                    'status' => 'published',
                    'show_on_home' => true,
                    'updated_at' => $now,
                ]);
        }
    }

    private static function seedFormConfig(int $tenantId, $now): void
    {
        DB::table('form_configs')->insert([
            'tenant_id' => $tenantId,
            'form_key' => 'expert_lead',
            'title' => 'Project brief',
            'description' => 'Expert inquiry (CRM: expert_service_inquiry)',
            'is_enabled' => true,
            'recipient_email' => null,
            'success_message' => 'Thank you — received. Expect a substantive reply shortly.',
            'error_message' => 'Could not send right now — try again shortly.',
            'fields_json' => json_encode([
                'goal_text' => ['label' => 'Brief', 'required' => true],
                'company' => ['label' => 'Company', 'required' => false],
                'briefing_website' => ['label' => 'Website', 'required' => false],
                'industry' => ['label' => 'Industry', 'required' => false],
                'budget_band' => ['label' => 'Budget', 'required' => false],
                'timeline_horizon' => ['label' => 'Timeline', 'required' => false],
                'comment' => ['label' => 'Context', 'required' => false],
            ]),
            'settings_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
