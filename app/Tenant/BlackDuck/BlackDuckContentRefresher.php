<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelConfig;
use App\ContactChannels\TenantContactChannelsStore;
use App\Http\Controllers\HomeController;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Models\TenantSetting;
use App\PageBuilder\Contacts\MapDisplayMode;
use App\PageBuilder\Contacts\MapInputMode;
use App\PageBuilder\Contacts\MapProvider;
use App\Support\Storage\TenantStorage;
use App\TenantSiteSetup\BookingNotificationsQuestionnaireRepository;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Идемпотентное обновление настроек и контента тенанта Black Duck (Q1), вне ensureSections.
 */
final class BlackDuckContentRefresher
{
    public function __construct(
        private readonly TenantContactChannelsStore $contactChannels,
        private readonly BookingNotificationsQuestionnaireRepository $bookingQuestionnaire,
    ) {}

    public function resolveBlackDuckTenant(): ?Tenant
    {
        $t = Tenant::query()
            ->where('theme_key', BlackDuckContentConstants::THEME_KEY)
            ->first();
        if ($t !== null) {
            return $t;
        }

        return Tenant::query()
            ->whereIn('slug', BlackDuckContentConstants::SLUGS)
            ->first();
    }

    public function refreshSettings(
        Tenant $tenant,
        bool $dryRun,
        bool $onlySeo,
        bool $onlyContacts,
        bool $onlyBranding,
    ): int {
        $id = (int) $tenant->id;

        if ($dryRun) {
            return 0;
        }

        if ($onlySeo) {
            $this->applySeo($tenant);
            HomeController::forgetCachedPayloadForTenant($id);

            return 0;
        }

        $runAll = ! $onlyContacts && ! $onlyBranding;
        if ($runAll || $onlyContacts) {
            $this->writeContactSettings($id);
            $this->writeBookingAndForms($id);
            $this->persistContactChannelsState($id);
        }
        if ($runAll || $onlyBranding) {
            $this->writeGeneralBranding($id);
        }

        if ($runAll || $onlyBranding) {
            $this->resyncBrandingAssetPathsIfFilesExist($id);
        }

        $this->applySeo($tenant);
        HomeController::forgetCachedPayloadForTenant($id);
        TenantSetting::setForTenant(
            $id,
            BlackDuckContentConstants::SETTING_FINGERPRINT_KEY,
            (string) now()->getTimestamp(),
            'string',
        );

        return 0;
    }

    private function writeContactSettings(int $tenantId): void
    {
        TenantSetting::setForTenant($tenantId, 'contacts.phone', BlackDuckContentConstants::PHONE_DISPLAY, 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.email', BlackDuckContentConstants::EMAIL, 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.whatsapp', '79123050015', 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.telegram', '@'.BlackDuckContentConstants::TELEGRAM_HANDLE, 'string');
        if (BlackDuckContentConstants::URL_VK !== '') {
            TenantSetting::setForTenant($tenantId, 'contacts.vk_url', BlackDuckContentConstants::URL_VK, 'string');
        }
        TenantSetting::setForTenant($tenantId, 'contacts.address', BlackDuckContentConstants::ADDRESS_PUBLIC, 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.public_office_address', BlackDuckContentConstants::ADDRESS_PUBLIC, 'string');
        TenantSetting::setForTenant($tenantId, 'contacts.hours', BlackDuckContentConstants::HOURS_TEXT, 'string');
    }

    private function writeGeneralBranding(int $tenantId): void
    {
        TenantSetting::setForTenant(
            $tenantId,
            'general.domain',
            rtrim(BlackDuckContentConstants::CANONICAL_PUBLIC_BASE_URL, '/'),
            'string',
        );
        TenantSetting::setForTenant(
            $tenantId,
            'general.site_name',
            BlackDuckContentConstants::PUBLIC_SITE_NAME,
            'string',
        );
        TenantSetting::setForTenant(
            $tenantId,
            'general.short_description',
            BlackDuckContentConstants::PUBLIC_SHORT_DESCRIPTION,
            'string',
        );
        TenantSetting::setForTenant(
            $tenantId,
            'general.footer_tagline',
            BlackDuckContentConstants::PUBLIC_FOOTER_TAGLINE,
            'string',
        );
    }

    private function writeBookingAndForms(int $tenantId): void
    {
        $merged = $this->bookingQuestionnaire->getMerged($tenantId);
        $merged['dest_email'] = BlackDuckContentConstants::EMAIL;
        $merged['meta_brand_name'] = 'Black Duck Detailing';
        $merged['meta_timezone'] = 'Asia/Yekaterinburg';
        $this->bookingQuestionnaire->save($tenantId, $merged);

        $fcId = (int) DB::table('form_configs')
            ->where('tenant_id', $tenantId)
            ->where('form_key', 'expert_lead')
            ->value('id');
        if ($fcId > 0) {
            DB::table('form_configs')->where('id', $fcId)->update([
                'recipient_email' => BlackDuckContentConstants::EMAIL,
                'updated_at' => now(),
            ]);
        }
    }

    private function persistContactChannelsState(int $tenantId): void
    {
        $state = $this->contactChannels->resolvedState($tenantId);
        $raw = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $c = $state[$k] ?? new TenantContactChannelConfig;
            $row = $c->toArray();
            if ($k === ContactChannelType::Phone->value) {
                $row['uses_channel'] = true;
                $row['public_visible'] = true;
                $row['allowed_in_forms'] = $row['allowed_in_forms'] || true;
                $row['business_value'] = BlackDuckContentConstants::PHONE_DISPLAY;
            }
            if ($k === ContactChannelType::Whatsapp->value) {
                $row['uses_channel'] = true;
                $row['public_visible'] = true;
                $row['allowed_in_forms'] = true;
                $row['business_value'] = '79123050015';
            }
            if ($k === ContactChannelType::Telegram->value) {
                $row['uses_channel'] = true;
                $row['public_visible'] = true;
                $row['allowed_in_forms'] = true;
                $row['business_value'] = '@'.BlackDuckContentConstants::TELEGRAM_HANDLE;
            }
            if ($k === ContactChannelType::Vk->value) {
                $vkUrl = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.vk_url', ''));
                if ($vkUrl !== '' && $vkUrl !== 'https://vk.com/') {
                    $row['uses_channel'] = true;
                    $row['public_visible'] = true;
                    $row['allowed_in_forms'] = true;
                    $row['business_value'] = $vkUrl;
                }
            }
            $raw[$k] = $row;
        }
        $this->contactChannels->persist($tenantId, $raw);
    }

    public function importBrandLogoFromPath(Tenant $tenant, string $absoluteSource, bool $dryRun): ?string
    {
        if (! is_file($absoluteSource)) {
            return null;
        }
        if ($dryRun) {
            return 'dry-run';
        }
        $ts = TenantStorage::forTrusted($tenant);
        $bytes = (string) file_get_contents($absoluteSource);
        $logical = BlackDuckContentConstants::LOGO_LOGICAL;
        $ext = strtolower(pathinfo($absoluteSource, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }
        $ok = $ts->putPublic($logical, $bytes, [
            'ContentType' => match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            'visibility' => 'public',
        ]);
        if (! $ok) {
            return null;
        }
        $fullKey = $ts->publicPath($logical);
        TenantSetting::setForTenant((int) $tenant->id, 'branding.logo_path', $fullKey, 'string');
        $heroFile = 'site/brand/hero.'.$ext;
        if ($ts->putPublic($heroFile, $bytes, [
            'ContentType' => match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            'visibility' => 'public',
        ])) {
            $heroKey = $ts->publicPath($heroFile);
            TenantSetting::setForTenant((int) $tenant->id, 'branding.hero_path', $heroKey, 'string');
        }
        HomeController::forgetCachedPayloadForTenant((int) $tenant->id);

        return $fullKey;
    }

    /**
     * Копирует единый фон шапки посадочных услуг в {@code site/brand/service-landing-hero.{ext}} и обновляет секции hero.
     */
    public function importServiceLandingHeaderFromFile(Tenant $tenant, string $absolutePath, bool $dryRun): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }
        if ($dryRun) {
            return 'dry-run';
        }
        $ext = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'], true)) {
            return null;
        }
        $bytes = @file_get_contents($absolutePath);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }
        $ts = TenantStorage::forTrusted($tenant);
        $logical = BlackDuckContentConstants::SERVICE_LANDING_HEADER_STEM.'.'.$ext;
        $contentType = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            default => 'image/jpeg',
        };
        if (! $ts->putPublic($logical, $bytes, [
            'ContentType' => $contentType,
            'visibility' => 'public',
        ])) {
            return null;
        }
        $this->resyncBrandingAssetPathsIfFilesExist((int) $tenant->id);
        $this->updateHomeSections((int) $tenant->id, true, false, 'expert_hero');
        $this->updateServiceLandings((int) $tenant->id, true, false, 'hero');
        HomeController::forgetCachedPayloadForTenant((int) $tenant->id);

        return $ts->publicPath($logical);
    }

    /**
     * WebP+JPEG бандл шапки: {@see BlackDuckHomeHeroBundle::STORAGE_LOGICAL} из каталога с файлами {@code hero-1916.webp}, …
     *
     * @return array<string, string> role => logical; пусто при ошибке
     */
    public function importHomeHeroWebpBundleFromDirectory(Tenant $tenant, string $absoluteDir, bool $dryRun): array
    {
        $out = BlackDuckHomeHeroBundle::importFromDirectory($tenant, $absoluteDir, $dryRun);
        if ($dryRun) {
            return $out;
        }
        if ($out === []) {
            return [];
        }
        $this->resyncBrandingAssetPathsIfFilesExist((int) $tenant->id);
        $this->updateHomeSections((int) $tenant->id, true, false, 'expert_hero');
        $this->updateServiceLandings((int) $tenant->id, true, false, 'hero');
        HomeController::forgetCachedPayloadForTenant((int) $tenant->id);

        return $out;
    }

    /**
     * @param  array{force: bool, if_placeholder: bool, only_seo: bool, force_section: ?string, dry_run: bool}  $opts
     */
    public function refreshContent(Tenant $tenant, array $opts): void
    {
        $id = (int) $tenant->id;
        $force = (bool) ($opts['force'] ?? false);
        $ifPlaceholder = (bool) ($opts['if_placeholder'] ?? true);
        $onlySeo = (bool) ($opts['only_seo'] ?? false);
        $forceSection = isset($opts['force_section']) ? (string) $opts['force_section'] : null;
        $dry = (bool) ($opts['dry_run'] ?? false);

        if ($dry) {
            return;
        }

        if ($force && ! $onlySeo && $tenant->theme_key === 'black_duck') {
            $this->removeExpertLeadFormSections((int) $tenant->id);
            $this->pruneUnwantedHomeSections((int) $tenant->id);
            $this->ensureBlackDuckStructuralPageSections((int) $tenant->id);
        }

        if ($onlySeo) {
            $this->applySeo($tenant);

            return;
        }

        if ($this->shouldRunFaqs($id, $force, $ifPlaceholder)) {
            $this->replaceFaqs($id);
        }
        if ($this->isBlackDuckTenant($id)) {
            $this->seedServiceLandingFaqStubs($id);
        }

        if ($this->shouldRunReviews($id, $force, $ifPlaceholder)) {
            $this->replaceReviews($id);
        }
        if ($this->shouldSeedBlackDuckMapsCuratedReviews($id, $force)) {
            $this->seedBlackDuckMapsCuratedReviews($id);
        }

        if ($this->isBlackDuckTenant($id)) {
            (new BlackDuckServicePageSync)->syncForTenant($id);
        }

        $this->updateHomeSections($id, $force, $ifPlaceholder, $forceSection);
        if ($tenant->theme_key === 'black_duck') {
            $this->syncHomeResultsSections($id, $force, $ifPlaceholder, $forceSection);
        }
        $this->updateServiceHub($id, $force, $ifPlaceholder, $forceSection);
        $this->updateServiceLandings($id, $force, $ifPlaceholder, $forceSection);
        if ($tenant->theme_key === 'black_duck') {
            $this->syncServiceProofGalleries($id, $force, $ifPlaceholder, $forceSection);
        }
        $this->updateRabotyPage($id, $force, $ifPlaceholder, $forceSection);
        $this->updateReviewsPage($id, $force, $ifPlaceholder, $forceSection);
        $this->updateContactsPage($id, $force, $ifPlaceholder, $forceSection);
        $this->updatePromoAndPrivacy($id, $force, $ifPlaceholder, $forceSection);
        $this->resyncBrandingAssetPathsIfFilesExist($id);
        $this->applySeo($tenant);
        HomeController::forgetCachedPayloadForTenant($id);
    }

    /**
     * Убирает встроенные expert_lead_form: заявка живёт на /contacts (см. {@see BlackDuckContentConstants::PRIMARY_LEAD_URL}).
     */
    private function removeExpertLeadFormSections(int $tenantId): void
    {
        DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('section_key', 'expert_lead_form')
            ->delete();
    }

    /**
     * Удаляет с главной устаревшие секции-конструктор (кроме expert_lead — удаляется {@see removeExpertLeadFormSections}).
     */
    private function pruneUnwantedHomeSections(int $tenantId): void
    {
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'home')
            ->value('id');
        if ($pageId < 1) {
            return;
        }
        DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->whereIn('section_key', ['vehicle_class', 'package_matrix'])
            ->delete();
    }

    /**
     * Секция {@code messenger_capture_bar} (ключ messenger) дублирует футер; на главной не показываем.
     */
    private function hideHomeMessengerCaptureBarSection(int $tenantId): void
    {
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'home')
            ->value('id');
        if ($pageId < 1) {
            return;
        }
        DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', 'messenger')
            ->update(['is_visible' => false, 'updated_at' => now()]);
    }

    /**
     * Добавляет недостающие page_sections на /raboty и на посадочных услуг (существующие тенанты после --force).
     */
    private function ensureBlackDuckStructuralPageSections(int $tenantId): void
    {
        $now = now();
        $ins = $this->blackDuckStructuralSectionInserter($tenantId, $now);
        $this->ensureBlackDuckRabotyStructuralPageSections($tenantId, $ins, $now);
        $this->ensureBlackDuckServiceLandingStructuralPageSections($tenantId, $ins);
    }

    /**
     * @param  \Closure(int, string, string, string, int, array): void  $ins
     */
    private function ensureBlackDuckRabotyStructuralPageSections(int $tenantId, Closure $ins, $now): void
    {
        $pageIdRaboty = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'raboty')->value('id');
        if ($pageIdRaboty < 1) {
            return;
        }
        $needWorks = ! DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageIdRaboty)
            ->where('section_key', 'works_hero')
            ->exists();
        if ($needWorks) {
            DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $pageIdRaboty)
                ->where('section_key', 'case_list')
                ->update(['sort_order' => 20, 'updated_at' => $now]);
        }
        $ins($pageIdRaboty, 'works_hero', 'hero', 'Видео', 0, [
            'variant' => 'full_background',
            'heading' => 'Работы Black Duck',
            'subheading' => 'Фрагменты этапов и итогов.',
            'button_text' => 'Заявка и расчёт',
            'button_url' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'video_src' => '',
            'video_poster' => '',
            'overlay_dark' => true,
        ]);
        $ins($pageIdRaboty, 'works_portfolio', 'works_portfolio', 'Портфолио', 5, [
            'heading' => 'Портфолио',
            'intro' => '',
            'filters' => [],
            'gallery_items' => [],
            'primary_cta_label' => 'Заявка и расчёт',
            'primary_cta_href' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
        ]);
        $ins($pageIdRaboty, 'works_before_after', 'before_after_slider', 'До / после', 10, [
            'heading' => 'До и после',
            'pairs' => [],
        ]);
        $ins($pageIdRaboty, 'works_cta', 'rich_text', 'Связь', 40, [
            'content' => '<p class="text-zinc-300">Готовы обсудить работу? <a class="font-medium text-[#36C7FF] underline" href="'.e(BlackDuckContentConstants::PRIMARY_LEAD_URL).'">Оставьте заявку</a> — ответим и согласуем план.</p>',
        ]);
    }

    /**
     * @param  \Closure(int, string, string, string, int, array): void  $ins
     */
    private function ensureBlackDuckServiceLandingStructuralPageSections(int $tenantId, Closure $ins): void
    {
        foreach (BlackDuckServiceProgramCatalog::serviceProofTargetLandingSlugs($tenantId) as $slug) {
            $pId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($pId < 1) {
                continue;
            }
            $ins($pId, 'service_proof', 'case_study_cards', 'На фото', 40, [
                'heading' => 'На фото',
                'items' => [],
            ]);
        }
        foreach (BlackDuckServiceProgramCatalog::asRegistryShapedRows($tenantId) as $reg) {
            if (! $reg['has_landing'] || str_starts_with((string) $reg['slug'], '#')) {
                continue;
            }
            $slug = (string) $reg['slug'];
            $pId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($pId < 1) {
                continue;
            }
            $ins($pId, 'body_intro', 'rich_text', 'О услуге', 8, [
                'content' => '',
            ]);
            $ins($pId, 'service_included', 'list_block', 'Что входит', 12, [
                'title' => 'Что входит',
                'variant' => 'bullets',
                'items' => [
                    ['title' => 'Согласование', 'text' => 'Объём и срок после осмотра или заявки.'],
                ],
            ]);
            $ins($pId, 'service_faq', 'faq', 'FAQ', 25, [
                'section_heading' => 'Вопросы по услуге',
                'source' => 'faqs_table_service',
                'faq_category' => $slug,
                'items' => [],
            ]);
            $ins($pId, 'service_review_feed', 'review_feed', 'Отзывы', 27, [
                'heading' => 'Отзывы клиентов',
                'subheading' => 'Выдержки с 2ГИС и Яндекс Карт по этой услуге.',
                'layout' => 'service_maps_compact',
                'limit' => BlackDuckMapsReviewCatalog::REVIEWS_PER_LANDING,
                'category_key' => $slug,
                'section_id' => 'bd-service-reviews',
                'maps_link_2gis' => BlackDuckContentConstants::URL_2GIS_REVIEWS_TAB,
                'maps_link_yandex' => BlackDuckContentConstants::URL_YANDEX_MAPS_REVIEWS_TAB,
                'show_maps_cta' => true,
            ]);
            $ins($pId, 'service_final_cta', 'rich_text', 'Заявка', 50, [
                'content' => '<p class="text-zinc-300">Нужен расчёт или запись? <a class="font-medium text-[#36C7FF] underline" href="'.e(BlackDuckContentConstants::PRIMARY_LEAD_URL).'">Оставьте заявку</a> — согласуем детали.</p>',
            ]);
        }
    }

    private function blackDuckStructuralSectionInserter(int $tenantId, $now): Closure
    {
        return function (int $pId, string $key, string $type, string $title, int $sort, array $data) use ($tenantId, $now): void {
            $ex = DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $pId)
                ->where('section_key', $key)
                ->exists();
            if ($ex) {
                return;
            }
            DB::table('page_sections')->insert([
                'page_id' => $pId,
                'tenant_id' => $tenantId,
                'section_key' => $key,
                'section_type' => $type,
                'title' => $title,
                'sort_order' => $sort,
                'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}',
                'is_visible' => true,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };
    }

    /**
     * Подставляет branding.logo_path / hero_path по фактическим {@code site/brand/logo.*} и {@code hero-1916.*} / легаси {@code hero.*} (после rekey id или копий на R2).
     */
    private function resyncBrandingAssetPathsIfFilesExist(int $tenantId): void
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $this->setTenantSettingIfFileExists(
            $tenantId,
            $ts,
            'branding.logo_path',
            [
                'site/brand/logo.jpg', 'site/brand/logo.jpeg', 'site/brand/logo.png', 'site/brand/logo.webp',
            ],
        );
        $this->setTenantSettingIfFileExists(
            $tenantId,
            $ts,
            'branding.hero_path',
            [
                'site/brand/hero-1916.jpg',
                'site/brand/hero-1916.webp',
                'site/brand/hero.png',
                'site/brand/hero.jpg',
                'site/brand/hero.jpeg',
                'site/brand/hero.webp',
            ],
        );
    }

    /**
     * @param  list<string>  $candidates
     */
    private function setTenantSettingIfFileExists(
        int $tenantId,
        TenantStorage $ts,
        string $setting,
        array $candidates,
    ): void {
        foreach ($candidates as $p) {
            if ($ts->existsPublic($p)) {
                TenantSetting::setForTenant($tenantId, $setting, $ts->publicPath($p), 'string');
                break;
            }
        }
    }

    private function shouldRunFaqs(int $tenantId, bool $force, bool $ifPlaceholder): bool
    {
        if ($force) {
            return true;
        }
        if (! $ifPlaceholder) {
            return true;
        }
        $q = (string) DB::table('faqs')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->value('question');

        return $q === 'Сколько длится мойка премиум-класса?';
    }

    private function replaceFaqs(int $tenantId): void
    {
        DB::table('faqs')
            ->where('tenant_id', $tenantId)
            ->where(function ($q): void {
                $q->whereNull('category')->orWhere('category', 'general');
            })
            ->delete();
        $now = now();
        $rows = [
            ['Можно ли записаться онлайн?', '<p>Короткие услуги с онлайн-расписанием (например, детейлинг-мойка) — через свободные слоты на сайте, когда бокс и расписание подключены. Многоэтапные работы (PPF, керамика, крупная химчистка, тонировка) согласуются сменой: оставьте заявку в разделе «Контакты» или позвоните — подберём окно визита и план. Срочные вопросы в рабочее время — по телефону на сайте.</p>'],
            ['Как записаться и что быстрее: мойка или керамика?', '<p>Короткие работы (в т.ч. детейлинг-мойка) — по свободным слотам в онлайн-записи, когда расписание включено. Многоэтапные работы (керамика, PPF, крупная химчистка) согласуются сменой и планом после заявки или осмотра.</p>'],
            ['Нужен ли осмотр до фиксирования цены?', '<p>Для защитных и кузовных работ с разными зонами ЛКП — да, по согласованию: на месте или по договорённости по фото. Типовые пакеты можно оценить ориентировочно в переписке.</p>'],
            ['Как сочетаются винил и PPF?', '<p>Это разные задачи: винил — дизайн/переклейка, PPF — прозрачная защита лака. Состав пакета выбираем под цель, комбинировать можно — порядок слоёв и сроки согласуем в центре.</p>'],
            ['Сколько по времени занимает тонировка и работа с оптикой?', '<p>Зависит от объёма стёкол и плёнки. Ориентир дадут мастера после согласования комплекта; визит планируем так, чтобы не гадать «на глаз».</p>'],
            ['Есть ли гарантия на керамику и плёнки?', '<p>Условия гарантии и регламент ухода обсуждаем при сдаче работ и фиксируем по чек-листу, без устных «на словах».</p>'],
        ];
        $sort = 0;
        foreach ($rows as [$q, $a]) {
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

    /**
     * Черновые FAQ по услуге (category = service slug); не затираются {@see replaceFaqs()}.
     */
    private function seedServiceLandingFaqStubs(int $tenantId): void
    {
        $now = now();
        $prioritySlugs = array_flip(BlackDuckServiceRegistry::priorityLandingSlugsForRichFaq());
        foreach (BlackDuckServiceProgramCatalog::asRegistryShapedRows($tenantId) as $row) {
            if (! $row['has_landing'] || str_starts_with((string) $row['slug'], '#')) {
                continue;
            }
            $slug = (string) $row['slug'];
            $title = (string) $row['title'];
            $mode = (string) ($row['booking_mode'] ?? 'confirm');
            $bookingHow = match ($mode) {
                'instant' => 'Когда на сайте включены слоты и онлайн-запись — бронируйте свободное окно. Если слотов нет или нужны уточнения, напишите через раздел «Контакты» или позвоните — согласуем визит.',
                'quote' => 'Оставьте заявку в разделе «Контакты» с кратким описанием задачи — согласуем объём, сроки и смету, после чего назначим визит или осмотр.',
                default => 'Оставьте заявку в разделе «Контакты» или позвоните: согласуем удобное окно визита и при необходимости осмотр до старта работ.',
            };
            $stubPairs = [
                ['Как записаться на «'.$title.'»?', $bookingHow],
                ['Как понять итоговую цену и срок?', 'Ориентир «от» и рамки по прайсу или в переписке; точная смета и срок — после осмотра или по согласованному чек-листу, без скрытых позиций.'],
            ];
            $hasAny = DB::table('faqs')
                ->where('tenant_id', $tenantId)
                ->where('category', $slug)
                ->exists();
            if (! isset($prioritySlugs[$slug]) && $hasAny) {
                continue;
            }
            $ordered = isset($prioritySlugs[$slug])
                ? array_merge(BlackDuckPriorityServiceFaq::pairsForSlug($slug), $stubPairs)
                : $stubPairs;
            $seen = [];
            $pairs = [];
            foreach ($ordered as [$q, $a]) {
                $k = mb_strtolower(trim($q));
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $pairs[] = [$q, $a];
            }
            $existingLower = DB::table('faqs')
                ->where('tenant_id', $tenantId)
                ->where('category', $slug)
                ->pluck('question')
                ->map(static fn ($q): string => mb_strtolower(trim((string) $q)))
                ->all();
            $existingSet = array_fill_keys($existingLower, true);
            $sort = (int) (DB::table('faqs')
                ->where('tenant_id', $tenantId)
                ->where('category', $slug)
                ->max('sort_order') ?? 0);
            foreach ($pairs as [$q, $a]) {
                $k = mb_strtolower(trim($q));
                if (isset($existingSet[$k])) {
                    continue;
                }
                DB::table('faqs')->insert([
                    'tenant_id' => $tenantId,
                    'question' => $q,
                    'answer' => $a,
                    'category' => $slug,
                    'sort_order' => ($sort += 10),
                    'status' => 'published',
                    'show_on_home' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $existingSet[$k] = true;
            }
        }
    }

    private function shouldRunReviews(int $tenantId, bool $force, bool $ifPlaceholder): bool
    {
        if (! Schema::hasTable('reviews')) {
            return false;
        }
        if ($force) {
            return true;
        }
        $count = (int) DB::table('reviews')->where('tenant_id', $tenantId)->count();
        if ($count < 1) {
            return true;
        }
        if (! $ifPlaceholder) {
            return false;
        }
        $bootstrapNames = (int) DB::table('reviews')
            ->where('tenant_id', $tenantId)
            ->whereIn('name', ['Анна', 'Илья', 'Команда'])
            ->count();

        return $bootstrapNames >= 2;
    }

    private function replaceReviews(int $tenantId): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }
        // Не трогаем {@see BlackDuckMapsReviewCatalog::SOURCE}: отзывы с карт и правки в админке.
        DB::table('reviews')->where('tenant_id', $tenantId)->whereIn('source', ['site', 'import'])->delete();
        $now = now();
        $curated = [
            ['name' => 'Сергей', 'headline' => 'PPF и керамика', 'text_short' => 'Зона риска закрыта ровно, кромки не бросаются в глаз.', 'text_long' => 'Сделали перед и частично бок, керамика по верху — вода уходит, грязь не въедается так, как раньше.'],
            ['name' => 'Марина', 'headline' => 'Химчистка + кожа', 'text_short' => 'Салон дышит, без «химии» в запахе.', 'text_long' => 'Светлая кожа и тёмные сиденья — вывели пятна аккуратно, не пересушили, руль приятно держать.'],
            ['name' => 'Дмитрий', 'headline' => 'Тонировка', 'text_short' => 'Ровная линия кромки, в салоне комфортнее летом.', 'text_long' => 'Подобрали плотность под задачу, без пузырей и перекоса.'],
            ['name' => 'Ольга', 'headline' => 'Полировка + подготовка', 'text_short' => 'ЛКП стал чище, царапины визуально ушли в норму.', 'text_long' => 'Объяснили, что реально снять абразивом, что останется — без фантазий.'],
            ['name' => 'Игорь', 'headline' => 'Предпродажа', 'text_short' => 'Собрали внешний вид под осмотр покупателю.', 'text_long' => 'Комплекс мойка+косметика+мелкие косяки, отчитались по чек-листу, что важно при продаже.'],
            ['name' => 'Екатерина', 'headline' => 'Консультация', 'text_short' => 'Объяснили варианты без навязывания лишнего.', 'text_long' => 'Разложили, что сейчас смысленно по ЛКП, а что можно отложить — спокойно и по делу.'],
        ];
        foreach (array_values($curated) as $index => $r) {
            DB::table('reviews')->insert(array_merge($r, [
                'tenant_id' => $tenantId,
                'text' => $r['text_long'],
                'category_key' => 'service',
                'city' => 'Челябинск',
                'rating' => 5,
                'media_type' => 'text',
                'is_featured' => $index < 3,
                'sort_order' => ($index + 1) * 10,
                'date' => $now->toDateString(),
                'source' => 'site',
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function shouldSeedBlackDuckMapsCuratedReviews(int $tenantId, bool $force): bool
    {
        if (! $this->isBlackDuckTenant($tenantId) || ! Schema::hasTable('reviews')) {
            return false;
        }
        if ($force) {
            return true;
        }

        return (int) DB::table('reviews')
            ->where('tenant_id', $tenantId)
            ->where('source', BlackDuckMapsReviewCatalog::SOURCE)
            ->count() < 1;
    }

    private function seedBlackDuckMapsCuratedReviews(int $tenantId): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }
        DB::table('reviews')
            ->where('tenant_id', $tenantId)
            ->where('source', BlackDuckMapsReviewCatalog::SOURCE)
            ->delete();
        $rows = BlackDuckMapsReviewCatalog::rowsForDatabaseSeed($tenantId);
        if ($rows === []) {
            return;
        }
        $now = now();
        foreach ($rows as $row) {
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

    private function sectionMatch(?string $key, ?string $forceSection): bool
    {
        if ($forceSection === null || $forceSection === '') {
            return true;
        }

        return $key === $forceSection;
    }

    private function shouldUpdateJson(
        string $dataJson,
        bool $force,
        bool $ifPlaceholder,
    ): bool {
        if ($force) {
            return true;
        }
        if (! $ifPlaceholder) {
            return true;
        }

        return $this->jsonContainsBootstrapMarker($dataJson);
    }

    private function bypassForBlackDuckServiceCatalogDerived(int $tenantId): bool
    {
        return $this->isBlackDuckTenant($tenantId)
            && BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId);
    }

    private function jsonContainsBootstrapMarker(string $json): bool
    {
        if ($json === '') {
            return true;
        }
        $markers = [
            'hello@example.local',
            '(351) 200-00-00',
            '200-00-00',
            'запись и согласование въезда',
            'Премиальный детейлинг в Челябинске: защита ЛКП',
            'Собраны с публичных источников и с сайта',
            'Настоящий текст — заглушка Q1',
            'заглушка Q1',
            'Доверяйте свой автомобиль только профессионалам', // home expert_hero, чтобы подтягивать hero_image_url без --force
        ];
        foreach ($markers as $m) {
            if (str_contains($json, $m)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function readHomeSectionDataArray(int $tenantId, string $pageSlug, string $sectionKey): array
    {
        $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $pageSlug)->value('id');
        if ($pageId < 1) {
            return [];
        }
        $row = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', $sectionKey)
            ->value('data_json');
        if (! is_string($row) || $row === '') {
            return [];
        }
        $d = json_decode($row, true);

        return is_array($d) ? $d : [];
    }

    /**
     * Карточки «Проекты» на /raboty, заполненные вне медиакаталога (Filament, fill-case-study-cards): не затирать пустым {@see BlackDuckMediaCatalog::worksStoryCardItems} при --force.
     *
     * @param  list<mixed>  $items
     */
    private function rabotyCaseListItemsLookEditorial(array $items): bool
    {
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            if (trim((string) ($it['image_url'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function updateSectionData(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        array $data,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
        bool $bypassJsonPlaceholderHeuristic = false,
    ): void {
        if (! $this->sectionMatch($sectionKey, $forceSection)) {
            return;
        }
        $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $pageSlug)->value('id');
        if ($pageId < 1) {
            return;
        }
        $row = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', $sectionKey)
            ->first();
        if ($row === null) {
            return;
        }
        $enc = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
        $cur = (string) $row->data_json;
        $doUpdate = $bypassJsonPlaceholderHeuristic
            || $this->shouldUpdateJson($cur, $force, $ifPlaceholder);
        if (! $doUpdate) {
            return;
        }
        DB::table('page_sections')
            ->where('id', (int) $row->id)
            ->update([
                'data_json' => $enc,
                'updated_at' => now(),
            ]);
    }

    private function isBlackDuckTenant(int $tenantId): bool
    {
        return (string) DB::table('tenants')->where('id', $tenantId)->value('theme_key') === 'black_duck';
    }

    private function updateHomeSections(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $isBlackDuck = $this->isBlackDuckTenant($tenantId);
        $hero = [
            'heading' => 'Black Duck Detailing',
            'subheading' => 'Аккуратный детейлинг: честная оценка объёма, сроки и спокойный результат на ЛКП и в салоне',
            'description' => BlackDuckContentConstants::taglineLong(),
            'hero_eyebrow' => 'Детейлинг · Челябинск',
            'hero_image_alt' => 'Black Duck Detailing, детейлинг-центр в Челябинске',
            'primary_cta_label' => 'Записаться',
            'primary_cta_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'secondary_cta_label' => 'Смотреть работы',
            'secondary_cta_anchor' => BlackDuckContentConstants::WORKS_PAGE_URL,
            'trust_badges' => [
                ['text' => 'Челябинск, Артиллерийская 117/10'],
                ['text' => 'Осмотр и смета до старта сложных работ'],
                ['text' => 'Портфолио на сайте — ракурсы работ, не обещания'],
            ],
        ];
        $bundle = BlackDuckHomeHeroBundle::heroSectionFragmentForTenant($tenantId);
        if ($bundle !== null) {
            $hero = array_merge($hero, $bundle);
        } else {
            $homeHeroLogical = BlackDuckServiceImages::firstHomeExpertHeroLogicalPath($tenantId);
            if ($homeHeroLogical !== null) {
                $hero['hero_image_url'] = $homeHeroLogical;
            }
        }
        $this->updateSectionData($tenantId, 'home', 'expert_hero', $hero, $force, $ifPlaceholder, $forceSection);

        $this->updateSectionData($tenantId, 'home', 'availability_ribbon', [
            'text' => 'Режим: '.BlackDuckContentConstants::HOURS_TEXT.' Сложные работы согласуются заранее; быстрые услуги — по слотам в расписании.',
        ], $force, $ifPlaceholder, $forceSection);

        $this->updateSectionData($tenantId, 'home', 'sticky_cta', [
            'enabled' => true,
            'label_call' => 'Позвонить',
            'label_messenger' => 'Написать',
            'label_book' => 'Запись',
            'label_quote' => 'Расчёт',
            'book_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'quote_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
        ], $force, $ifPlaceholder, $forceSection);

        if ($this->sectionMatch('messenger', $forceSection)) {
            $this->hideHomeMessengerCaptureBarSection($tenantId);
        }

        $bypassServiceDerived = $this->bypassForBlackDuckServiceCatalogDerived($tenantId);
        $hubItems = [];
        if ($isBlackDuck && BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId)) {
            foreach (BlackDuckServiceProgramCatalog::homePreviewRowsExcludingPseudopages($tenantId) as $row) {
                $slug = (string) $row['slug'];
                $hasLanding = (bool) ($row['has_landing'] ?? true);
                $cta = BlackDuckServiceProgramCatalog::primaryCardCtaUrl($slug, $hasLanding);
                $img = BlackDuckServiceProgramCatalog::publicServiceHubCardImageLogicalPath($tenantId, $slug);
                $blurb = (string) ($row['blurb'] ?? '');
                $sub = BlackDuckServiceProgramCatalog::homeCardSubtitle($tenantId, $slug, $blurb);
                $anchor = BlackDuckServiceProgramCatalog::publicPriceAnchorForSlug($tenantId, $slug);
                $priceFrom = $anchor ?? (str_starts_with($slug, '#') ? 'по запросу' : 'по оценке');
                $bm = (string) ($row['booking_mode'] ?? '');
                [$online, $needsConf] = BlackDuckServiceProgramCatalog::bookingUIFromMode($bm);
                $hubItems[] = [
                    'title' => $row['title'],
                    'card_subtitle' => $sub,
                    'price_from' => $priceFrom,
                    'duration' => 'по плану',
                    'online_booking' => $online,
                    'needs_confirmation' => $needsConf,
                    'booking_mode' => $bm,
                    'cta_url' => $cta,
                    'book_url' => BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug),
                    'service_slug' => $slug,
                    'image_url' => $img ?? '',
                ];
            }
        } else {
            $previewSub = BlackDuckContentConstants::homeServiceCardPreviewSubtitlesBySlug();
            foreach (BlackDuckContentConstants::serviceMatrixHomePreview() as $row) {
                $slug = (string) $row['slug'];
                if ($isBlackDuck) {
                    $img = BlackDuckServiceProgramCatalog::publicServiceHubCardImageLogicalPath($tenantId, $slug);
                } else {
                    $img = BlackDuckServiceImages::firstExistingPublicPath($tenantId, $slug);
                }
                $sub = (string) ($previewSub[$slug] ?? $row['blurb']);
                $anchor = BlackDuckServiceRegistry::publicPriceAnchorForSlug($slug);
                $priceFrom = $anchor ?? (str_starts_with($slug, '#') ? 'по запросу' : 'по оценке');
                $bm = (string) $row['booking_mode'];
                [$online, $needsConf] = BlackDuckServiceProgramCatalog::bookingUIFromMode($bm);
                $hasLanding = (bool) ($row['has_landing'] ?? true);
                $cta = BlackDuckServiceProgramCatalog::primaryCardCtaUrl($slug, $hasLanding);
                $hubItems[] = [
                    'title' => $row['title'],
                    'card_subtitle' => $sub,
                    'price_from' => $priceFrom,
                    'duration' => 'по плану',
                    'online_booking' => $online,
                    'needs_confirmation' => $needsConf,
                    'booking_mode' => $bm,
                    'cta_url' => $cta,
                    'book_url' => BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug),
                    'service_slug' => $slug,
                    'image_url' => $img ?? '',
                ];
            }
        }
        $this->updateSectionData($tenantId, 'home', 'service_hub', [
            'heading' => 'Услуги детейлинга',
            'items' => $hubItems,
        ], $force, $ifPlaceholder, $forceSection, $bypassServiceDerived);
        if ($isBlackDuck && BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId) && $this->sectionMatch('service_hub', $forceSection)) {
            $this->applyBlackDuckServiceHubEmptyVisibility($tenantId, 'home', $hubItems === []);
        }

        if ($isBlackDuck) {
            $homeBa = BlackDuckMediaCatalog::homeBeforeAfterPairs($tenantId);
            if ($homeBa !== []) {
                $this->updateSectionData($tenantId, 'home', 'before_after', [
                    'heading' => 'Результат в деталях',
                    'subheading' => 'Одна пара до/после с объекта — без визуального шума. Полный каталог кадров в портфолио.',
                    'proof_works_cta_label' => 'Смотреть работы',
                    'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                    'pairs' => $homeBa,
                ], $force, $ifPlaceholder, $forceSection);
                $this->updateSectionData($tenantId, 'home', 'case_cards', [
                    'heading' => 'Свежие проекты',
                    'subheading' => 'Короткие описания задач; детали и ракурсы — в разделе работ.',
                    'proof_works_cta_label' => 'Смотреть работы',
                    'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                    'items' => [],
                ], $force, $ifPlaceholder, $forceSection);
            } else {
                $this->updateSectionData($tenantId, 'home', 'before_after', [
                    'heading' => 'Результат в деталях',
                    'subheading' => 'Пару «до/после» добавим после приёмки медиа в каталоге; пока смотрите свежие проекты ниже или в портфолио.',
                    'proof_works_cta_label' => 'Смотреть работы',
                    'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                    'pairs' => [],
                ], $force, $ifPlaceholder, $forceSection);
                $this->updateSectionData($tenantId, 'home', 'case_cards', [
                    'heading' => 'Свежие проекты',
                    'subheading' => 'Короткие описания задач; детали и ракурсы — в разделе работ.',
                    'proof_works_cta_label' => 'Смотреть работы',
                    'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                    'items' => BlackDuckMediaCatalog::homeCaseCardItems($tenantId),
                ], $force, $ifPlaceholder, $forceSection);
            }
        } else {
            $baKeep = $this->readHomeSectionDataArray($tenantId, 'home', 'before_after');
            $this->updateSectionData($tenantId, 'home', 'before_after', [
                'heading' => 'Результат в деталях',
                'subheading' => 'Одна пара до/после или подборка кейсов — по материалам в каталоге.',
                'proof_works_cta_label' => 'Смотреть работы',
                'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                'pairs' => is_array($baKeep['pairs'] ?? null) ? $baKeep['pairs'] : [],
            ], $force, $ifPlaceholder, $forceSection);

            $this->updateSectionData($tenantId, 'home', 'case_cards', [
                'heading' => 'Свежие проекты',
                'subheading' => 'Короткие описания задач; детали — в разделе работ.',
                'proof_works_cta_label' => 'Смотреть работы',
                'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
                'items' => [
                    [
                        'vehicle' => 'Кроссовер',
                        'task' => 'PPF зоны риска + керамика',
                        'result' => 'Защита ЛКП и устойчивый глянец в зоне работ.',
                        'duration' => 'по плану',
                    ],
                    [
                        'vehicle' => 'Седан',
                        'task' => 'Химчистка, кожа, полировка фар',
                        'result' => 'Свежий салон, прозрачнее оптика, аккуратный кузов.',
                        'duration' => '1–2 дня',
                    ],
                    [
                        'vehicle' => 'SUV',
                        'task' => 'Тонировка с подбором плёнки',
                        'result' => 'Ровные кромки, комфорт в салоне.',
                        'duration' => 'по смене',
                    ],
                ],
            ], $force, $ifPlaceholder, $forceSection);
        }

        $this->updateSectionData($tenantId, 'home', 'reviews', [
            'heading' => 'Отзывы',
            'subheading' => 'Короткие впечатления на сайте; развёрнутые оценки — в карточках карт (страница «Отзывы»).',
            'layout' => 'grid',
            'limit' => 6,
            'category_key' => 'service',
        ], $force, $ifPlaceholder, $forceSection);
    }

    private function updateServiceHub(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $isBlackDuck = $this->isBlackDuckTenant($tenantId);
        $hasCatalog = $isBlackDuck && BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId);
        $bypass = $this->bypassForBlackDuckServiceCatalogDerived($tenantId);
        $dbPrograms = $hasCatalog
            ? BlackDuckServiceProgramCatalog::visibleProgramsOrdered($tenantId)
            : collect();

        $rowFromProgram = static function (TenantServiceProgram $p): array {
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];

            return [
                'slug' => (string) $p->slug,
                'title' => (string) $p->title,
                'blurb' => (string) ($p->teaser ?? ''),
                'booking_mode' => (string) ($meta['booking_mode'] ?? ''),
                'has_landing' => (bool) ($meta['has_landing'] ?? true),
                '_group_key' => trim((string) ($meta['group_key'] ?? '')),
                '_group_title' => trim((string) ($meta['group_title'] ?? 'Услуги')),
                '_group_blurb' => trim((string) ($meta['group_blurb'] ?? '')),
                '_group_sort' => (int) ($meta['group_sort'] ?? 0),
                '_show_in_catalog' => (bool) ($meta['show_in_catalog'] ?? true),
            ];
        };

        $buildCard = function (array $row) use ($tenantId, $isBlackDuck): array {
            $slug = (string) $row['slug'];
            if ($isBlackDuck) {
                $img = BlackDuckServiceProgramCatalog::publicServiceHubCardImageLogicalPath($tenantId, $slug);
            } else {
                $img = BlackDuckServiceImages::firstExistingPublicPath($tenantId, $slug);
            }
            $hasLanding = (bool) ($row['has_landing'] ?? true);
            $cta = BlackDuckServiceProgramCatalog::primaryCardCtaUrl($slug, $hasLanding);
            $bm = (string) ($row['booking_mode'] ?? '');
            [$online, $needsConf] = BlackDuckServiceProgramCatalog::bookingUIFromMode($bm);
            $anchor = BlackDuckServiceProgramCatalog::publicPriceAnchorForSlug($tenantId, $slug);
            $priceFrom = $anchor ?? (str_starts_with($slug, '#') ? 'по запросу' : 'по оценке');

            return [
                'title' => $row['title'],
                'card_subtitle' => (string) $row['blurb'],
                'price_from' => $priceFrom,
                'duration' => 'по плану',
                'online_booking' => $online,
                'needs_confirmation' => $needsConf,
                'booking_mode' => $bm,
                'cta_url' => $cta,
                'book_url' => BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug),
                'service_slug' => $slug,
                'image_url' => $img ?? '',
            ];
        };
        $groupsPayload = [];
        if ($hasCatalog) {
            $byGroup = [];
            foreach ($dbPrograms as $p) {
                $r = $rowFromProgram($p);
                if (! $r['_show_in_catalog']) {
                    continue;
                }
                $gk = $r['_group_key'] !== '' ? $r['_group_key'] : 'services';
                if (! isset($byGroup[$gk])) {
                    $byGroup[$gk] = [
                        'group_key' => $gk,
                        'title' => $r['_group_title'] !== '' ? $r['_group_title'] : 'Услуги',
                        'intro' => $r['_group_blurb'],
                        '_sort' => (int) $r['_group_sort'],
                        'items' => [],
                    ];
                }
                $byGroup[$gk]['items'][] = $buildCard($r);
            }
            uasort($byGroup, static fn (array $a, array $b): int => ((int) $a['_sort']) <=> ((int) $b['_sort']));
            foreach ($byGroup as $g) {
                unset($g['_sort']);
                $groupsPayload[] = $g;
            }
        } elseif ($isBlackDuck) {
            foreach (BlackDuckServiceRegistry::catalogGroupsWithPlaceholderItems() as $g) {
                $cards = [];
                foreach ($g['items'] as $meta) {
                    $slug = (string) $meta['slug'];
                    $reg = BlackDuckServiceRegistry::rowBySlug($slug);
                    if ($reg === null) {
                        continue;
                    }
                    $legacy = [
                        'slug' => $reg['slug'],
                        'title' => $reg['title'],
                        'blurb' => $reg['blurb'],
                        'booking_mode' => $reg['booking_mode'],
                        'has_landing' => $reg['has_landing'],
                    ];
                    $cards[] = $buildCard($legacy);
                }
                $groupsPayload[] = [
                    'group_key' => (string) $g['group_key'],
                    'title' => (string) $g['group_title'],
                    'intro' => (string) $g['group_blurb'],
                    'items' => $cards,
                ];
            }
        }
        $items = [];
        if ($hasCatalog) {
            foreach ($dbPrograms as $p) {
                $r = $rowFromProgram($p);
                if (! $r['_show_in_catalog']) {
                    continue;
                }
                $items[] = $buildCard($r);
            }
        } else {
            foreach (BlackDuckContentConstants::serviceMatrixQ1() as $row) {
                $items[] = $buildCard($row);
            }
        }
        $this->updateSectionData($tenantId, 'uslugi', 'intro', [
            'content' => '<p class="lead">'.e(BlackDuckContentConstants::taglineLong()).'</p>',
        ], $force, $ifPlaceholder, $forceSection, $bypass);
        $hubData = [
            'heading' => 'Услуги детейлинга',
            'items' => $items,
        ];
        if ($groupsPayload !== []) {
            $hubData['groups'] = $groupsPayload;
        }
        $this->updateSectionData($tenantId, 'uslugi', 'service_hub', $hubData, $force, $ifPlaceholder, $forceSection, $bypass);
        if ($hasCatalog && $this->sectionMatch('service_hub', $forceSection)) {
            $empty = $items === [] && $groupsPayload === [];
            $this->applyBlackDuckServiceHubEmptyVisibility($tenantId, 'uslugi', $empty);
        }
    }

    /**
     * {@see BlackDuckServicePageSync} прячет саму страницу; сюда снимаем публичные секции, чтобы «висячий» URL не тянул старый визуал/отзывы.
     *
     * Саму страницу (404/redirect) и прямой GET по /slug без этого синка не гарантируйте: если sync страниц пропустили, остаётся риск «пустой живой» URL. Поведение public route — в синке/роутинге.
     *
     * @param  ?string  $forceSection  при непустом значении, как в {@see updatePageSectionVisibility}, трогаем только ключи, для которых {@see sectionMatch} — чтобы узкий refresh не менял visibility чужих секций.
     */
    private function concealPublicSectionsForDeactivatedServiceLandings(int $tenantId, ?string $forceSection = null): void
    {
        if (! $this->isBlackDuckTenant($tenantId) || ! BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId)) {
            return;
        }
        $sectionKeys = [
            'service_proof',
            'service_review_feed',
            'service_faq',
            'hero',
            'body_intro',
            'service_included',
            'body',
            'service_final_cta',
        ];
        if ($forceSection !== null && $forceSection !== '') {
            $sectionKeys = array_values(array_filter(
                $sectionKeys,
                fn (string $k): bool => $this->sectionMatch($k, $forceSection),
            ));
            if ($sectionKeys === []) {
                return;
            }
        }
        foreach (BlackDuckServiceProgramCatalog::allProgramsOrdered($tenantId) as $p) {
            $slug = (string) $p->slug;
            if (str_starts_with($slug, '#')) {
                continue;
            }
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
            $hasLanding = (bool) ($meta['has_landing'] ?? true);
            if ($p->is_visible && $hasLanding) {
                continue;
            }
            $pageId = (int) DB::table('pages')
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->value('id');
            if ($pageId < 1) {
                continue;
            }
            DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $pageId)
                ->whereIn('section_key', $sectionKeys)
                ->update(['is_visible' => false, 'updated_at' => now()]);
        }
    }

    /**
     * После {@see concealPublicSectionsForDeactivatedServiceLandings} при реактивации услуги `updateSectionData` не трогает is_visible;
     * возвращаем видимость только always-on структурных секций (по одному ключу, с {@see sectionMatch} для {@code forceSection}).
     */
    private function restoreAlwaysOnStructuralSectionVisibilityForActiveServiceLanding(
        int $tenantId,
        string $pageSlug,
        ?string $forceSection,
    ): void {
        if (! $this->isBlackDuckTenant($tenantId) || ! BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId)) {
            return;
        }
        $structuralKeys = ['hero', 'body_intro', 'service_included', 'service_final_cta'];
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', $pageSlug)
            ->value('id');
        if ($pageId < 1) {
            return;
        }
        foreach ($structuralKeys as $sectionKey) {
            if (! $this->sectionMatch($sectionKey, $forceSection)) {
                continue;
            }
            DB::table('page_sections')
                ->where('tenant_id', $tenantId)
                ->where('page_id', $pageId)
                ->where('section_key', $sectionKey)
                ->update(['is_visible' => true, 'updated_at' => now()]);
        }
    }

    /**
     * На /home и /uslugi: при пустой витрине скрывает секцию {@code service_hub}.
     * Вызывать только при {@see sectionMatch}('service_hub', $forceSection), иначе узкий refresh затирает visibility.
     */
    private function applyBlackDuckServiceHubEmptyVisibility(int $tenantId, string $pageSlug, bool $hubIsEmpty): void
    {
        if (! $this->isBlackDuckTenant($tenantId) || ! BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId)) {
            return;
        }
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', $pageSlug)
            ->value('id');
        if ($pageId < 1) {
            return;
        }
        DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', 'service_hub')
            ->update([
                'is_visible' => $hubIsEmpty ? false : true,
                'updated_at' => now(),
            ]);
    }

    private function updateServiceLandings(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        if ($this->isBlackDuckTenant($tenantId)) {
            $hasCatalog = BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId);
            $bypassL = $this->bypassForBlackDuckServiceCatalogDerived($tenantId);
            $programs = $hasCatalog
                ? BlackDuckServiceProgramCatalog::visibleProgramsOrdered($tenantId)
                : collect();

            $registry = $hasCatalog ? [] : BlackDuckServiceRegistry::all();
            $iter = $hasCatalog ? $programs : $registry;

            foreach ($iter as $entry) {
                $reg = $hasCatalog
                    ? (function (TenantServiceProgram $p): array {
                        $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];

                        return [
                            'slug' => (string) $p->slug,
                            'title' => (string) $p->title,
                            'blurb' => (string) ($p->teaser ?? ''),
                            'body_intro' => (string) ($p->description ?? ''),
                            'booking_mode' => (string) ($meta['booking_mode'] ?? ''),
                            'has_landing' => (bool) ($meta['has_landing'] ?? true),
                            'included_items' => is_array($meta['included_items'] ?? null) ? $meta['included_items'] : [],
                        ];
                    })($entry)
                    : $entry;

                if (! ($reg['has_landing'] ?? false)) {
                    continue;
                }
                $slug = (string) $reg['slug'];
                $name = (string) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('name');
                if ($name === '' && $hasCatalog) {
                    $name = (string) ($reg['title'] ?? '');
                }
                if ($name === '') {
                    continue;
                }
                $lead = (string) $reg['blurb'];
                $hero = [
                    'variant' => 'full_background',
                    'heading' => $name,
                    'subheading' => $lead,
                    'button_text' => 'Состав и этапы',
                    'button_url' => '#bd-service-included',
                    'secondary_button_text' => 'Записаться',
                    'secondary_button_url' => BlackDuckContentConstants::serviceLandingBookIntentUrl($slug),
                    'overlay_dark' => true,
                ];
                $bg = BlackDuckServiceProgramCatalog::serviceLandingHeroBackgroundLogicalPath($tenantId, $slug);
                if ($bg !== null) {
                    $hero['background_image'] = $bg;
                }
                $svcVid = BlackDuckMediaCatalog::serviceFeaturedVideoMedia($tenantId, $slug);
                if ($svcVid !== []) {
                    $hero['video_src'] = $svcVid['video'];
                    $hero['video_poster'] = $svcVid['poster'];
                    $hero['video_deferred'] = true;
                }
                $this->updateSectionData($tenantId, $slug, 'hero', $hero, $force, $ifPlaceholder, $forceSection, $bypassL);
                $intro = trim((string) $reg['body_intro']);
                $this->updateSectionData($tenantId, $slug, 'body_intro', [
                    'content' => $intro !== ''
                        ? '<p class="text-pretty leading-relaxed text-zinc-200 sm:text-base">'.e($intro).'</p>'
                        : '<p class="text-pretty leading-relaxed text-zinc-200 sm:text-base">'.e($lead).'</p>',
                ], $force, $ifPlaceholder, $forceSection, $bypassL);
                $incl = [];
                foreach ($reg['included_items'] as $it) {
                    if (! is_array($it)) {
                        continue;
                    }
                    $incl[] = [
                        'title' => (string) ($it['title'] ?? ''),
                        'text' => (string) ($it['text'] ?? ''),
                    ];
                }
                $this->updateSectionData($tenantId, $slug, 'service_included', [
                    'title' => 'Что входит',
                    'variant' => 'bullets',
                    'items' => $incl !== [] ? $incl : [
                        ['title' => 'Согласование', 'text' => 'Объём и срок после осмотра или заявки.'],
                    ],
                ], $force, $ifPlaceholder, $forceSection, $bypassL);
                $this->updateSectionData($tenantId, $slug, 'body', [
                    'content' => '',
                ], $force, $ifPlaceholder, $forceSection, $bypassL);
                $this->updatePageSectionVisibility($tenantId, $slug, 'body', false, $forceSection);
                $this->updateSectionData($tenantId, $slug, 'service_faq', [
                    'section_heading' => 'Вопросы по услуге',
                    'source' => 'faqs_table_service',
                    'faq_category' => $slug,
                    'items' => [],
                ], $force, $ifPlaceholder, $forceSection, $bypassL);
                $faqN = (int) DB::table('faqs')
                    ->where('tenant_id', $tenantId)
                    ->where('category', $slug)
                    ->where('status', 'published')
                    ->count();
                $this->updatePageSectionVisibility($tenantId, $slug, 'service_faq', $faqN > 0, $forceSection);
                $this->updateSectionData($tenantId, $slug, 'service_review_feed', [
                    'heading' => 'Отзывы клиентов',
                    'subheading' => 'Выдержки с 2ГИС и Яндекс Карт по этой услуге. Пятая карточка — ссылки на полные подборки на картах.',
                    'layout' => 'service_maps_compact',
                    'limit' => BlackDuckMapsReviewCatalog::REVIEWS_PER_LANDING,
                    'category_key' => $slug,
                    'section_id' => 'bd-service-reviews',
                    'maps_link_2gis' => BlackDuckContentConstants::URL_2GIS_REVIEWS_TAB,
                    'maps_link_yandex' => BlackDuckContentConstants::URL_YANDEX_MAPS_REVIEWS_TAB,
                    'show_maps_cta' => true,
                ], $force, $ifPlaceholder, $forceSection, $bypassL);
                $mapsRevN = (int) DB::table('reviews')
                    ->where('tenant_id', $tenantId)
                    ->where('category_key', $slug)
                    ->where('source', BlackDuckMapsReviewCatalog::SOURCE)
                    ->where('status', 'published')
                    ->count();
                $this->updatePageSectionVisibility($tenantId, $slug, 'service_review_feed', $mapsRevN > 0, $forceSection);
                $inquiry = BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug);
                $this->updateSectionData($tenantId, $slug, 'service_final_cta', [
                    'content' => '<p class="text-zinc-300">Нужен расчёт или запись? <a class="font-medium text-[#36C7FF] underline" href="'.e($inquiry).'">Оставьте заявку</a> — в форме можно выбрать услугу «'.e($name).'».</p>',
                ], $force, $ifPlaceholder, $forceSection, $bypassL);
                if ($hasCatalog) {
                    $this->restoreAlwaysOnStructuralSectionVisibilityForActiveServiceLanding(
                        $tenantId,
                        $slug,
                        $forceSection,
                    );
                }
            }
            if ($hasCatalog) {
                $this->concealPublicSectionsForDeactivatedServiceLandings($tenantId, $forceSection);
            }

            return;
        }
        $leads = [
            'detejling-mojka' => 'Короткий цикл: запись в онлайн-расписании при включённых слотах. Длительность зависит от класса кузова и пакета.',
            'setki-radiatora' => 'Сетки на радиатор: подбор по геометрии, крепёж без вибрации; сроки — по согласованию.',
            'antidozhd' => 'Покрытия для стёкол с гидрофобным эффектом; составы и срок службы согласуем.',
            'remont-skolov' => 'Точечный ремонт лаков и сколов, чтобы не тянуть весь кузов «на покрас» раньше времени.',
            'himchistka-salona' => 'Салон, кожа, ткань: сроки и глубина чистки — после осмотра и теста материалов.',
            'kozha-keramika' => 'Пропитка/керамика по коже после теста и чистки; совместимость с фактурой важнее «универсалки».',
            'polirovka-kuzova' => 'Полировка и финиш по состоянию ЛКП; оценим риск перегрева/остатка дефектов заранее.',
            'keramika' => 'Керамическое покрытие: серия этапов, график согласуем, контроль в инфо-ленте у мастеров.',
            'restavratsiya-kozhi' => 'Реставрация кожи: пигмент, лак, швы — по плану после осмотра зон.',
            'himchistka-diskov' => 'Снятие грязи и суппорт-зон; без рисков по ЛКП диска — важно в проёме калипера.',
            'bronirovanie-salona' => 'Плёнки и защитные наборы на пластик, дисплеи, пороги; расклад по приоритету зон.',
            'himchistka-kuzova' => 'Наружный хим-чек: деинкрустация и подготовка под план (полировка/керамика/PPF).',
            'ppf' => 'Полиуретановая плёнка: зоны и макет по осмотру, стык и кромка — в фокусе.',
            'tonirovka' => 'Тонировка стёкол и бронеплёнка/тонировка оптики — по согласованной конфигурации и регламенту ГИБДД (при необходимости).',
            'shumka' => 'Шумоизоляция: план и стоимость — после разборки/диагностики шумовой задачи.',
            'podkapotnaya-himchistka' => 'Подкапотный блок: сухая/мокрая схема, консервация пластиков, маркировка снятых кожухов.',
            'pdr' => 'PDR: доступ к вмятине, клей/инструмент, иногда частичный съём — обсуждаем до старта.',
            'predprodazhnaya' => 'Предпродажа: внешний вид и документы по чек-листу для уверенного осмотра покупателем.',
        ];
        foreach ($leads as $slug => $lead) {
            $name = (string) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('name');
            if ($name === '') {
                continue;
            }
            $hero = [
                'variant' => 'full_background',
                'heading' => $name,
                'subheading' => $lead,
                'button_text' => 'Оставить заявку',
                'button_url' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                'overlay_dark' => true,
            ];
            $bg = BlackDuckServiceImages::firstServiceLandingShadePath($tenantId)
                ?? BlackDuckServiceImages::firstExistingPublicPath($tenantId, $slug);
            if ($bg !== null) {
                $hero['background_image'] = $bg;
            }
            $this->updateSectionData($tenantId, $slug, 'hero', $hero, $force, $ifPlaceholder, $forceSection);
            $this->updateSectionData($tenantId, $slug, 'body', [
                'content' => '<p>'.e($lead).' Сроки и стоимость фиксируем после осмотра и согласования плана, без сюрпризов в процессе.</p>',
            ], $force, $ifPlaceholder, $forceSection);
        }
    }

    private function syncHomeResultsSections(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        if ($forceSection !== null && $forceSection !== ''
            && ! $this->sectionMatch('before_after', $forceSection) && ! $this->sectionMatch('case_cards', $forceSection)) {
            return;
        }
        $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($pageId < 1) {
            return;
        }
        $baRow = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', 'before_after')
            ->first();
        $ccRow = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', 'case_cards')
            ->first();
        if ($baRow === null || $ccRow === null) {
            return;
        }
        $ba = json_decode((string) $baRow->data_json, true) ?: [];
        $cc = json_decode((string) $ccRow->data_json, true) ?: [];
        $pairs = is_array($ba['pairs'] ?? null) ? $ba['pairs'] : [];
        $hasBa = false;
        foreach ($pairs as $p) {
            if (! is_array($p)) {
                continue;
            }
            $b = trim((string) ($p['before_url'] ?? ''));
            $a = trim((string) ($p['after_url'] ?? ''));
            if ($b !== '' && $a !== ''
                && $this->blackDuckStoredVisualExists($tenantId, $b)
                && $this->blackDuckStoredVisualExists($tenantId, $a)) {
                $hasBa = true;
                break;
            }
        }
        $items = is_array($cc['items'] ?? null) ? $cc['items'] : [];
        $visual = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $img = trim((string) ($it['image_url'] ?? ''));
            if ($img === '' || ! $this->blackDuckStoredVisualExists($tenantId, $img)) {
                continue;
            }
            $visual[] = $it;
            if (count($visual) >= 3) {
                break;
            }
        }
        $hasCase = $visual !== [];
        $singleSection = $forceSection !== null && $forceSection !== ''
            && ($this->sectionMatch('before_after', $forceSection) xor $this->sectionMatch('case_cards', $forceSection));
        if ($singleSection) {
            if ($this->sectionMatch('before_after', $forceSection)) {
                $this->setPageSectionVisibilityById((int) $baRow->id, $hasBa);

                return;
            }
            if ($this->sectionMatch('case_cards', $forceSection)) {
                if ($hasCase && ! $hasBa) {
                    $cc['items'] = $visual;
                    $cc['proof_works_cta_label'] = $cc['proof_works_cta_label'] ?? 'Смотреть работы';
                    $cc['proof_works_cta_href'] = $cc['proof_works_cta_href'] ?? BlackDuckContentConstants::WORKS_PAGE_URL;
                    DB::table('page_sections')
                        ->where('id', (int) $ccRow->id)
                        ->update([
                            'data_json' => json_encode($cc, JSON_UNESCAPED_UNICODE) ?: '{}',
                            'is_visible' => true,
                            'updated_at' => now(),
                        ]);
                } else {
                    $this->setPageSectionVisibilityById((int) $ccRow->id, false);
                }

                return;
            }
        }
        if ($hasBa) {
            $this->setPageSectionVisibilityById((int) $baRow->id, true);
            $this->setPageSectionVisibilityById((int) $ccRow->id, false);
        } elseif ($hasCase) {
            $cc['items'] = $visual;
            $cc['proof_works_cta_label'] = $cc['proof_works_cta_label'] ?? 'Смотреть работы';
            $cc['proof_works_cta_href'] = $cc['proof_works_cta_href'] ?? BlackDuckContentConstants::WORKS_PAGE_URL;
            DB::table('page_sections')
                ->where('id', (int) $ccRow->id)
                ->update([
                    'data_json' => json_encode($cc, JSON_UNESCAPED_UNICODE) ?: '{}',
                    'is_visible' => true,
                    'updated_at' => now(),
                ]);
            $this->setPageSectionVisibilityById((int) $baRow->id, false);
        } else {
            $this->setPageSectionVisibilityById((int) $baRow->id, false);
            $this->setPageSectionVisibilityById((int) $ccRow->id, false);
        }
    }

    private function setPageSectionVisibilityById(int $sectionId, bool $visible): void
    {
        DB::table('page_sections')
            ->where('id', $sectionId)
            ->update(['is_visible' => $visible, 'updated_at' => now()]);
    }

    /**
     * @param  ?string  $forceSection  при непустом {@see $forceSection} обновляем только если совпал {@see $sectionKey} (как в {@see updateSectionData}).
     */
    private function updatePageSectionVisibility(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        bool $visible,
        ?string $forceSection = null,
    ): void {
        if (! $this->sectionMatch($sectionKey, $forceSection)) {
            return;
        }
        $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $pageSlug)->value('id');
        if ($pageId < 1) {
            return;
        }
        DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', $sectionKey)
            ->update(['is_visible' => $visible, 'updated_at' => now()]);
    }

    private function blackDuckStoredVisualExists(int $tenantId, string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $stored) === 1) {
            return false;
        }
        $path = $stored;
        if (! str_starts_with($path, 'site/')) {
            $path = 'site/brand/'.ltrim($path, '/');
        }

        return BlackDuckMediaCatalog::logicalPathIsUsable($tenantId, $path);
    }

    /**
     * Медиа-каталог — источник правды: если в каталоге есть элементы, секцию обновляем даже когда
     * {@see shouldUpdateJson} отказала бы (не заглушка, не --force), чтобы публичный block не оставался
     * со старым инвентарём. Пустой каталог: прежняя защита — не трогать, если плейсхолдер и пусто.
     */
    private function syncServiceProofGalleries(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        if ($forceSection !== null && $forceSection !== '' && ! $this->sectionMatch('service_proof', $forceSection)) {
            return;
        }
        foreach (BlackDuckServiceProgramCatalog::serviceProofTargetLandingSlugs($tenantId) as $slug) {
            $row = DB::table('page_sections as ps')
                ->join('pages as p', 'p.id', '=', 'ps.page_id')
                ->where('p.tenant_id', $tenantId)
                ->where('p.slug', $slug)
                ->where('ps.section_key', 'service_proof')
                ->select('ps.id', 'ps.data_json')
                ->first();
            if ($row === null) {
                continue;
            }
            $gallery = BlackDuckMediaCatalog::serviceGalleryDisplayItems($tenantId, $slug);
            $items = [];
            foreach ($gallery as $g) {
                $cap = (string) ($g['caption'] ?? '');
                $tit = (string) ($g['title'] ?? '');
                $sum = (string) ($g['summary'] ?? '');
                $alt = trim((string) ($g['alt'] ?? ''));
                if ($alt === '') {
                    $alt = $tit !== '' ? $tit : ($cap !== '' ? $cap : 'Фото работы');
                }
                $items[] = [
                    'vehicle' => '',
                    'task' => $tit !== '' ? $tit : $cap,
                    'title' => $tit,
                    'summary' => $sum,
                    'caption' => $cap,
                    'result' => '',
                    'duration' => '',
                    'image_url' => (string) $g['logical_path'],
                    'image_alt' => $alt,
                    'srcset' => (string) ($g['srcset'] ?? ''),
                    'sizes' => (string) ($g['sizes'] ?? ''),
                    'aspect_ratio' => $g['aspect_ratio'] ?? null,
                ];
            }
            $data = [
                'heading' => 'На фото',
                'items' => $items,
            ];
            $enc = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
            $cur = (string) $row->data_json;
            if (! $this->shouldUpdateJson($cur, $force, $ifPlaceholder) && $items === []) {
                continue;
            }
            DB::table('page_sections')->where('id', (int) $row->id)->update([
                'data_json' => $enc,
                'is_visible' => $items !== [],
                'updated_at' => now(),
            ]);
        }
    }

    private function updateRabotyPage(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $isBlackDuck = $this->isBlackDuckTenant($tenantId);
        if ($isBlackDuck) {
            $prevPortfolio = $this->readHomeSectionDataArray($tenantId, 'raboty', 'works_portfolio');
            $prevGalleryItems = is_array($prevPortfolio['gallery_items'] ?? null) ? $prevPortfolio['gallery_items'] : [];
            $prevCaseList = $this->readHomeSectionDataArray($tenantId, 'raboty', 'case_list');
            $prevCaseItems = is_array($prevCaseList['items'] ?? null) ? $prevCaseList['items'] : [];

            $grid = BlackDuckMediaCatalog::worksPortfolioGridItems($tenantId);
            $chips = BlackDuckMediaCatalog::worksPortfolioFilterChips($tenantId);
            $preservePortfolio = $grid === [] && $prevGalleryItems !== [];
            if (! $preservePortfolio) {
                $this->updateSectionData($tenantId, 'raboty', 'works_portfolio', [
                    'heading' => 'Портфолио',
                    'intro' => 'Подбор по направлениям. Подробный разбор и сроки — по заявке.',
                    'filters' => $chips,
                    'gallery_items' => $grid,
                    'primary_cta_label' => 'Заявка и расчёт',
                    'primary_cta_href' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
                ], $force, $ifPlaceholder, $forceSection);
                if ($this->sectionMatch('works_portfolio', $forceSection)) {
                    $this->updatePageSectionVisibility($tenantId, 'raboty', 'works_portfolio', $grid !== [], $forceSection);
                }
            } elseif ($this->sectionMatch('works_portfolio', $forceSection)) {
                $this->updatePageSectionVisibility($tenantId, 'raboty', 'works_portfolio', true, $forceSection);
            }

            $story = BlackDuckMediaCatalog::worksStoryCardItems($tenantId, 12);
            $preserveCaseList = $story === [] && $this->rabotyCaseListItemsLookEditorial($prevCaseItems);
            if (! $preserveCaseList) {
                $this->updateSectionData($tenantId, 'raboty', 'case_list', [
                    'heading' => 'Проекты',
                    'proof_works_cta_label' => '',
                    'proof_works_cta_href' => '',
                    'items' => $story,
                ], $force, $ifPlaceholder, $forceSection);
                if ($this->sectionMatch('case_list', $forceSection)) {
                    $this->updatePageSectionVisibility($tenantId, 'raboty', 'case_list', $story !== [], $forceSection);
                }
            } elseif ($this->sectionMatch('case_list', $forceSection)) {
                $this->updatePageSectionVisibility($tenantId, 'raboty', 'case_list', true, $forceSection);
            }
        } else {
            $this->updateSectionData($tenantId, 'raboty', 'case_list', [
                'heading' => 'Кейсы',
                'items' => [
                    [
                        'vehicle' => 'SUV, тёмный кузов',
                        'task' => 'PPF + керамика зоны риска',
                        'result' => 'Согласованный глянец, защита стыков; отчёт по этапам.',
                        'duration' => '2–3 дня',
                    ],
                    [
                        'vehicle' => 'Седан',
                        'task' => 'Сложная химчистка + кожа',
                        'result' => 'Свежий салон, без «химического» запаха, ровная фактура кожи.',
                        'duration' => '1–2 дня',
                    ],
                    [
                        'vehicle' => 'Кроссовер',
                        'task' => 'Тонировка заднего сегмента',
                        'result' => 'Ровные кромки, комфорт и приватность в ряду с регламентом.',
                        'duration' => 'по смене',
                    ],
                ],
            ], $force, $ifPlaceholder, $forceSection);
        }
        $vid = BlackDuckMediaCatalog::worksFeaturedHeroMedia($tenantId);
        $wHero = [
            'variant' => 'full_background',
            'heading' => 'Работы Black Duck',
            'subheading' => 'Фрагменты этапов и итогов. Подбор по направлению — на месте и по заявке.',
            'button_text' => 'Заявка и расчёт',
            'button_url' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'video_src' => (string) ($vid['video'] ?? ''),
            'video_poster' => (string) ($vid['poster'] ?? ''),
            'overlay_dark' => true,
        ];
        $bg = BlackDuckServiceImages::firstServiceLandingShadePath($tenantId);
        $hasWorksVideo = ($vid['video'] ?? '') !== '';
        if ($bg !== null) {
            if (! $hasWorksVideo) {
                $wHero['background_image'] = $bg;
            } elseif (($wHero['video_poster'] ?? '') === '') {
                $wHero['video_poster'] = $bg;
                $wHero['background_image'] = $bg;
            }
        }
        $this->updateSectionData($tenantId, 'raboty', 'works_hero', $wHero, $force, $ifPlaceholder, $forceSection);
        if ($isBlackDuck) {
            $wbPairs = BlackDuckMediaCatalog::worksBeforeAfterPairs($tenantId);
        } else {
            $wb = $this->readHomeSectionDataArray($tenantId, 'raboty', 'works_before_after');
            $wbPairs = is_array($wb['pairs'] ?? null) ? $wb['pairs'] : [];
        }
        $this->updateSectionData($tenantId, 'raboty', 'works_before_after', [
            'heading' => 'До и после',
            'proof_lead_href' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'proof_lead_label' => 'Согласовать проект',
            'pairs' => is_array($wbPairs) ? $wbPairs : [],
        ], $force, $ifPlaceholder, $forceSection);
        $this->updateSectionData($tenantId, 'raboty', 'works_cta', [
            'content' => '<p class="text-zinc-300">Готовы обсудить работу? <a class="font-medium text-[#36C7FF] underline" href="'.e(BlackDuckContentConstants::PRIMARY_LEAD_URL).'">Оставьте заявку</a> — ответим и согласуем план.</p>',
        ], $force, $ifPlaceholder, $forceSection);
    }

    private function updateReviewsPage(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $this->updateSectionData($tenantId, 'otzyvy', 'review_feed', [
            'heading' => 'Отзывы клиентов',
            'subheading' => 'Ниже — отобранные отзывы, опубликованные на сайте. Актуальные рейтинги и полные ленты смотрите в сервисах карт (ссылки под блоком).',
            'layout' => 'grid',
            'limit' => 24,
        ], $force, $ifPlaceholder, $forceSection);
        $a2 = htmlspecialchars(BlackDuckContentConstants::URL_2GIS, ENT_QUOTES, 'UTF-8');
        $aY = htmlspecialchars(BlackDuckContentConstants::URL_YANDEX_MAPS, ENT_QUOTES, 'UTF-8');
        $links = [
            '<a href="'.$a2.'" rel="noopener">2ГИС</a>',
            '<a href="'.$aY.'" rel="noopener">Яндекс.Карты</a>',
        ];
        $ig = BlackDuckContentConstants::instagramUrlForPublic();
        if ($ig !== '') {
            $links[] = '<a href="'.htmlspecialchars($ig, ENT_QUOTES, 'UTF-8').'" rel="noopener">Instagram</a>';
        }
        $this->ensureOrUpdateRichTextSection(
            $tenantId,
            'otzyvy',
            'external_social',
            10,
            'Ссылки на отзывы',
            '<p>Посмотреть отзывы и рейтинги в картосервисах: '.implode(' · ', $links).'.</p>',
            $force,
            $ifPlaceholder,
            $forceSection,
        );
    }

    private function updateContactsPage(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $igPublic = BlackDuckContentConstants::instagramUrlForPublic();
        $data = [
            'heading' => 'Контакты',
            'description' => 'Звонок, мессенджер или заявка на сайте. Точка въезда и смена согласуются с менеджером.',
            'phone' => BlackDuckContentConstants::PHONE_DISPLAY,
            'email' => BlackDuckContentConstants::EMAIL,
            'whatsapp' => 'https://wa.me/79123050015',
            'telegram' => 'https://t.me/'.BlackDuckContentConstants::TELEGRAM_HANDLE,
            'vk_url' => BlackDuckContentConstants::URL_VK,
            'address' => BlackDuckContentConstants::ADDRESS_PUBLIC,
            'map_enabled' => true,
            'map_provider' => MapProvider::Yandex->value,
            'map_combined_input' => BlackDuckContentConstants::URL_YANDEX_MAPS,
            'map_secondary_combined_input' => BlackDuckContentConstants::URL_2GIS,
            'map_input_mode' => MapInputMode::Auto->value,
            'map_display_mode' => MapDisplayMode::EmbedAndButton->value,
            'map_title' => 'Как добраться',
            'social_note' => $igPublic !== '' ? 'Instagram: '.$igPublic : '',
        ];
        $this->updateSectionData($tenantId, 'contacts', 'contacts', $data, $force, $ifPlaceholder, $forceSection);
        // requires_service_selector не перезаписываем: явный false в data_json сохраняется; дефолт для black_duck — в {@see \App\Services\PublicSite\ContactInquiryFormPresenter::sectionRequiresServiceSelector}.
        $this->updateSectionData($tenantId, 'contacts', 'contact_faq', [
            'title' => 'Частые вопросы',
            'items' => [
                [
                    'question' => 'Как доехать к центру?',
                    'answer' => '<p>'.e(BlackDuckContentConstants::ADDRESS_PUBLIC).'. Парковка и въезд — по согласованию, уточняйте у менеджера.</p>',
                ],
            ],
        ], $force, $ifPlaceholder, $forceSection);
    }

    private function updatePromoAndPrivacy(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $this->updateSectionData($tenantId, 'akcii', 'stub', [
            'content' => '<p>Акции и подарочные сертификаты публикуются по мере согласования. Оставьте заявку — пришлём актуальные варианты на '.e(BlackDuckContentConstants::EMAIL).'.</p>',
        ], $force, $ifPlaceholder, $forceSection);
        $this->updateSectionData($tenantId, 'privacy-policy', 'legal', [
            'content' => '<p>Обработка персональных данных при заявках и записях ведётся в соответствии с 152-ФЗ. Администратор: по запросу через контакты на сайте. Текст подлежит юридической доработке под вашу организационную структуру.</p>',
        ], $force, $ifPlaceholder, $forceSection);
    }

    private function ensureOrUpdateRichTextSection(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        int $sortOrder,
        string $title,
        string $html,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        if (! $this->sectionMatch($sectionKey, $forceSection)) {
            return;
        }
        $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $pageSlug)->value('id');
        if ($pageId < 1) {
            return;
        }
        $exists = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', $sectionKey)
            ->first();
        $payload = [
            'content' => $html,
        ];
        $enc = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
        if ($exists === null) {
            $now = now();
            DB::table('page_sections')->insert([
                'page_id' => $pageId,
                'tenant_id' => $tenantId,
                'section_key' => $sectionKey,
                'section_type' => 'rich_text',
                'title' => $title,
                'sort_order' => $sortOrder,
                'data_json' => $enc,
                'is_visible' => true,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }
        $cur = (string) $exists->data_json;
        if (! $this->shouldUpdateJson($cur, $force, $ifPlaceholder)) {
            return;
        }
        DB::table('page_sections')
            ->where('id', (int) $exists->id)
            ->update(['data_json' => $enc, 'updated_at' => now(), 'title' => $title, 'sort_order' => $sortOrder]);
    }

    /**
     * @throws JsonException
     */
    public function applySeo(Tenant $tenant): void
    {
        $tenantId = (int) $tenant->id;
        $homeId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
        if ($homeId < 1) {
            return;
        }
        $logoPath = (string) TenantSetting::getForTenant($tenantId, 'branding.logo_path', '');
        $publicBase = rtrim(BlackDuckContentConstants::CANONICAL_PUBLIC_BASE_URL, '/');

        $org = [
            '@type' => 'AutoRepair',
            '@id' => $publicBase.'/#org',
            'name' => 'Black Duck Detailing',
            'telephone' => BlackDuckContentConstants::PHONE_DISPLAY,
            'url' => $publicBase.'/',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'ул. Артиллерийская, 117/10',
                'addressLocality' => BlackDuckContentConstants::ADDRESS_CITY,
                'addressCountry' => 'RU',
            ],
        ];
        if ($logoPath !== '' && (str_starts_with($logoPath, 'tenants/') || str_contains($logoPath, '/public/'))) {
            $org['image'] = \tenant_branding_asset_url($logoPath, '');
            $org['logo'] = \tenant_branding_asset_url($logoPath, '');
        }
        $sameAs = array_values(array_filter([
            BlackDuckContentConstants::URL_2GIS,
            BlackDuckContentConstants::URL_YANDEX_MAPS,
            BlackDuckContentConstants::instagramUrlForPublic(),
        ]));
        if ($sameAs !== []) {
            $org['sameAs'] = $sameAs;
        }
        $wash = [
            '@type' => 'Service',
            'name' => 'Детейлинг-мойка',
            'serviceType' => 'Car wash and exterior detailing',
            'provider' => ['@id' => $publicBase.'/#org'],
            'areaServed' => [
                '@type' => 'City',
                'name' => BlackDuckContentConstants::ADDRESS_CITY,
            ],
        ];
        $jsonLd = [$org, $wash];

        SeoMeta::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'seoable_type' => Page::class,
                'seoable_id' => $homeId,
            ],
            [
                'meta_title' => 'Black Duck Detailing — детейлинг в Челябинске',
                'meta_description' => 'Винил, PPF, керамика, тонировка, химчистка, полировка. Заявка и запись, Артиллерийская 117/10.',
                'h1' => 'Black Duck Detailing',
                'og_title' => 'Black Duck Detailing',
                'og_description' => 'Детейлинг-центр в Челябинске. Защита ЛКП, салон, оптика.',
                'is_indexable' => true,
                'is_followable' => true,
                'json_ld' => $jsonLd,
            ],
        );

        $other = [
            'uslugi' => ['Услуги — Black Duck Detailing', 'Полный каталог: PPF, керамика, винил, тонировка, мойка, химчистка, полировка, подкапотное, сетки, антидождь, PDR и другое.'],
            'contacts' => ['Контакты — Black Duck Detailing', BlackDuckContentConstants::PHONE_DISPLAY.' · '.BlackDuckContentConstants::EMAIL],
            'faq' => ['Вопросы — Black Duck Detailing', 'Запись, сроки, осмотр, гарантийные вопросы.'],
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
}
