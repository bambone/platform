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
use App\Models\TenantSetting;
use App\PageBuilder\Contacts\MapDisplayMode;
use App\PageBuilder\Contacts\MapInputMode;
use App\PageBuilder\Contacts\MapProvider;
use App\Support\Storage\TenantStorage;
use App\TenantSiteSetup\BookingNotificationsQuestionnaireRepository;
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

        if ($this->shouldRunReviews($id, $force, $ifPlaceholder)) {
            $this->replaceReviews($id);
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
     * Добавляет новые page_sections на /raboty и service_proof на приоритетных лендингах (существующие тенанты после --force).
     */
    private function ensureBlackDuckStructuralPageSections(int $tenantId): void
    {
        $now = now();
        $pageIdRaboty = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'raboty')->value('id');
        if ($pageIdRaboty < 1) {
            return;
        }
        $ins = function (int $pId, string $key, string $type, string $title, int $sort, array $data) use ($tenantId, $now): void {
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
        $ins($pageIdRaboty, 'works_before_after', 'before_after_slider', 'До / после', 10, [
            'heading' => 'До и после',
            'pairs' => [],
        ]);
        $ins($pageIdRaboty, 'works_cta', 'rich_text', 'Связь', 40, [
            'content' => '<p class="text-zinc-300">Готовы обсудить работу? <a class="font-medium text-[#36C7FF] underline" href="'.e(BlackDuckContentConstants::PRIMARY_LEAD_URL).'">Оставьте заявку</a> — ответим и согласуем план.</p>',
        ]);
        foreach (BlackDuckMediaCatalog::SERVICE_PROOF_LANDING_SLUGS as $slug) {
            $pId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('id');
            if ($pId < 1) {
                continue;
            }
            $ins($pId, 'service_proof', 'case_study_cards', 'На фото', 40, [
                'heading' => 'На фото',
                'items' => [],
            ]);
        }
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
        DB::table('faqs')->where('tenant_id', $tenantId)->delete();
        $now = now();
        $rows = [
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

    private function updateSectionData(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        array $data,
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
        if (! $this->shouldUpdateJson($cur, $force, $ifPlaceholder)) {
            return;
        }
        DB::table('page_sections')
            ->where('id', (int) $row->id)
            ->update([
                'data_json' => $enc,
                'updated_at' => now(),
            ]);
    }

    private function updateHomeSections(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $hero = [
            'heading' => 'Black Duck Detailing',
            'subheading' => 'Доверяйте свой автомобиль только профессионалам',
            'description' => BlackDuckContentConstants::taglineLong(),
            'hero_eyebrow' => 'Детейлинг · Челябинск',
            'hero_image_alt' => 'Black Duck Detailing, детейлинг-центр в Челябинске',
            'primary_cta_label' => 'Записаться',
            'primary_cta_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'secondary_cta_label' => 'Получить расчёт',
            'secondary_cta_anchor' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'trust_badges' => [
                ['text' => 'Челябинск, Артиллерийская 117/10'],
                ['text' => 'Запись и согласование сложных работ'],
                ['text' => 'Онлайн-заявка и короткие слоты по расписанию'],
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

        $this->updateSectionData($tenantId, 'home', 'messenger', [
            'title' => 'Связь с центром',
            'subheading' => 'Заявка — основной путь. Быстрые ответы в мессенджерах.',
            'show_whatsapp' => true,
            'show_telegram' => true,
            'show_call' => true,
            'primary_lead_label' => 'Заявка на сайте',
            'primary_lead_href' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'works_cta_label' => 'Смотреть работы',
            'works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
        ], $force, $ifPlaceholder, $forceSection);

        $previewSub = BlackDuckContentConstants::homeServiceCardPreviewSubtitlesBySlug();
        $hubItems = [];
        foreach (BlackDuckContentConstants::serviceMatrixHomePreview() as $row) {
            $slug = (string) $row['slug'];
            $cta = str_starts_with($slug, '#') ? BlackDuckContentConstants::PRIMARY_LEAD_URL : '/'.$slug;
            $img = BlackDuckServiceImages::firstExistingPublicPath($tenantId, $slug);
            $sub = (string) ($previewSub[$slug] ?? $row['blurb']);
            $hubItems[] = [
                'title' => $row['title'],
                'card_subtitle' => $sub,
                'price_from' => 'по задаче',
                'duration' => 'по плану',
                'online_booking' => $row['slug'] === 'detejling-mojka',
                'needs_confirmation' => $row['slug'] !== 'detejling-mojka',
                'booking_mode' => (string) $row['booking_mode'],
                'cta_url' => $cta,
                'image_url' => $img ?? '',
            ];
        }
        $this->updateSectionData($tenantId, 'home', 'service_hub', [
            'heading' => 'Ключевые направления',
            'items' => $hubItems,
        ], $force, $ifPlaceholder, $forceSection);

        $baKeep = $this->readHomeSectionDataArray($tenantId, 'home', 'before_after');
        $this->updateSectionData($tenantId, 'home', 'before_after', [
            'heading' => 'Результат в деталях',
            'proof_works_cta_label' => 'Смотреть работы',
            'proof_works_cta_href' => BlackDuckContentConstants::WORKS_PAGE_URL,
            'pairs' => is_array($baKeep['pairs'] ?? null) ? $baKeep['pairs'] : [],
        ], $force, $ifPlaceholder, $forceSection);

        $this->updateSectionData($tenantId, 'home', 'case_cards', [
            'heading' => 'Свежие проекты',
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

        $this->updateSectionData($tenantId, 'home', 'reviews', [
            'heading' => 'Отзывы',
            'subheading' => 'Короткие отзывы на сайте; полные оценки и детальные ленты — в картах (см. страницу «Отзывы»).',
            'layout' => 'grid',
            'limit' => 6,
        ], $force, $ifPlaceholder, $forceSection);
    }

    private function updateServiceHub(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        $items = [];
        foreach (BlackDuckContentConstants::serviceMatrixQ1() as $row) {
            $slug = (string) $row['slug'];
            $img = BlackDuckServiceImages::firstExistingPublicPath($tenantId, $slug);
            $cta = str_starts_with($slug, '#') ? BlackDuckContentConstants::PRIMARY_LEAD_URL : '/'.$slug;
            $items[] = [
                'title' => $row['title'],
                'card_subtitle' => (string) $row['blurb'],
                'price_from' => 'по оценке',
                'duration' => 'по плану',
                'online_booking' => $row['slug'] === 'detejling-mojka',
                'needs_confirmation' => true,
                'booking_mode' => (string) $row['booking_mode'],
                'cta_url' => $cta,
                'image_url' => $img ?? '',
            ];
        }
        $this->updateSectionData($tenantId, 'uslugi', 'intro', [
            'content' => '<p class="lead">'.e(BlackDuckContentConstants::taglineLong()).'</p>',
        ], $force, $ifPlaceholder, $forceSection);
        $this->updateSectionData($tenantId, 'uslugi', 'service_hub', [
            'heading' => 'Услуги детейлинга',
            'items' => $items,
        ], $force, $ifPlaceholder, $forceSection);
    }

    private function updateServiceLandings(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
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

    private function blackDuckStoredVisualExists(int $tenantId, string $stored): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $stored) === 1) {
            return true;
        }
        $ts = TenantStorage::forTrusted($tenantId);
        $path = $stored;
        if (! str_starts_with($path, 'site/')) {
            $path = 'site/brand/'.ltrim($path, '/');
        }

        return $ts->existsPublic($path);
    }

    private function syncServiceProofGalleries(
        int $tenantId,
        bool $force,
        bool $ifPlaceholder,
        ?string $forceSection,
    ): void {
        if ($forceSection !== null && $forceSection !== '' && ! $this->sectionMatch('service_proof', $forceSection)) {
            return;
        }
        foreach (BlackDuckMediaCatalog::SERVICE_PROOF_LANDING_SLUGS as $slug) {
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
            $gallery = BlackDuckMediaCatalog::serviceGalleryImagePaths($tenantId, $slug);
            $items = [];
            foreach ($gallery as $g) {
                $items[] = [
                    'vehicle' => '',
                    'task' => (string) ($g['caption'] ?? ''),
                    'result' => '',
                    'duration' => '',
                    'image_url' => (string) $g['logical_path'],
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
        $vid = BlackDuckMediaCatalog::featuredVideoForPage($tenantId, 'raboty');
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
        if ($bg !== null && (($vid['video'] ?? '') === '')) {
            $wHero['background_image'] = $bg;
        }
        $this->updateSectionData($tenantId, 'raboty', 'works_hero', $wHero, $force, $ifPlaceholder, $forceSection);
        $wb = $this->readHomeSectionDataArray($tenantId, 'raboty', 'works_before_after');
        $this->updateSectionData($tenantId, 'raboty', 'works_before_after', [
            'heading' => 'До и после',
            'proof_lead_href' => BlackDuckContentConstants::PRIMARY_LEAD_URL,
            'proof_lead_label' => 'Согласовать проект',
            'pairs' => is_array($wb['pairs'] ?? null) ? $wb['pairs'] : [],
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
