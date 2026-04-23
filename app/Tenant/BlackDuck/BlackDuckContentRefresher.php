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
            'Black Duck Detailing',
            'string',
        );
        TenantSetting::setForTenant(
            $tenantId,
            'general.short_description',
            'Детейлинг-центр в Челябинске: PPF, керамика, тонировка, винил, химчистка, полировка.',
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
        $this->updateServiceHub($id, $force, $ifPlaceholder, $forceSection);
        $this->updateServiceLandings($id, $force, $ifPlaceholder, $forceSection);
        $this->updateCases($id, $force, $ifPlaceholder, $forceSection);
        $this->updateReviewsPage($id, $force, $ifPlaceholder, $forceSection);
        $this->updateContactsPage($id, $force, $ifPlaceholder, $forceSection);
        $this->updatePromoAndPrivacy($id, $force, $ifPlaceholder, $forceSection);
        $this->applySeo($tenant);
        HomeController::forgetCachedPayloadForTenant($id);
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
        foreach ($curated as $r) {
            DB::table('reviews')->insert(array_merge($r, [
                'tenant_id' => $tenantId,
                'text' => $r['text_long'],
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
        ];
        foreach ($markers as $m) {
            if (str_contains($json, $m)) {
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
            'primary_cta_anchor' => '#expert-inquiry',
            'secondary_cta_label' => 'Получить расчёт',
            'secondary_cta_anchor' => '#expert-inquiry',
            'trust_badges' => [
                ['text' => 'Челябинск, Артиллерийская 117/10'],
                ['text' => 'Запись и согласование сложных работ'],
                ['text' => 'Онлайн-заявка и короткие слоты по расписанию'],
            ],
        ];
        $this->updateSectionData($tenantId, 'home', 'expert_hero', $hero, $force, $ifPlaceholder, $forceSection);

        $this->updateSectionData($tenantId, 'home', 'availability_ribbon', [
            'text' => 'Режим: '.BlackDuckContentConstants::HOURS_TEXT.' Сложные работы согласуются заранее; быстрые услуги — по слотам в расписании.',
        ], $force, $ifPlaceholder, $forceSection);

        $hubItems = [];
        foreach (BlackDuckContentConstants::serviceMatrixQ1() as $row) {
            $slug = (string) $row['slug'];
            $cta = str_starts_with($slug, '#') ? $slug : '/'.$slug;
            $hubItems[] = [
                'title' => $row['title'],
                'price_from' => 'по задаче',
                'duration' => 'по плану',
                'online_booking' => $row['slug'] === 'detejling-mojka',
                'needs_confirmation' => $row['slug'] !== 'detejling-mojka',
                'cta_url' => $cta,
            ];
        }
        $this->updateSectionData($tenantId, 'home', 'service_hub', [
            'heading' => 'Услуги детейлинга',
            'items' => $hubItems,
        ], $force, $ifPlaceholder, $forceSection);

        $this->updateSectionData($tenantId, 'home', 'case_cards', [
            'heading' => 'Проекты',
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
        foreach (array_slice(BlackDuckContentConstants::serviceMatrixQ1(), 0, 8) as $row) {
            $slug = (string) $row['slug'];
            $items[] = [
                'title' => $row['title'],
                'price_from' => 'по оценке',
                'duration' => 'по плану',
                'online_booking' => $row['slug'] === 'detejling-mojka',
                'needs_confirmation' => true,
                'cta_url' => str_starts_with($slug, '#') ? $slug : '/'.$slug,
            ];
        }
        $this->updateSectionData($tenantId, 'uslugi', 'intro', [
            'content' => '<p class="lead">'.e(BlackDuckContentConstants::taglineLong()).'</p>',
        ], $force, $ifPlaceholder, $forceSection);
        $this->updateSectionData($tenantId, 'uslugi', 'service_hub', [
            'heading' => 'Каталог направлений',
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
            'himchistka-salona' => 'Салон, кожа, ткань: сроки и глубина чистки — после осмотра и теста материалов.',
            'polirovka-kuzova' => 'Полировка и финиш по состоянию ЛКП; оценим риск перегрева/остатка дефектов заранее.',
            'keramika' => 'Керамическое покрытие: серия этапов, график согласуем, контроль в инфо-ленте у мастеров.',
            'ppf' => 'Полиуретановая плёнка: зоны и макет по осмотру, стык и кромка — в фокусе.',
            'tonirovka' => 'Тонировка стёкол и бронеплёнка/тонировка оптики — по согласованной конфигурации и регламенту ГИБДД (при необходимости).',
            'shumka' => 'Шумоизоляция: план и стоимость — после разборки/диагностики шумовой задачи.',
            'pdr' => 'PDR: доступ к вмятине, клей/инструмент, иногда частичный съём — обсуждаем до старта.',
            'predprodazhnaya' => 'Предпродажа: внешний вид и документы по чек-листу для уверенного осмотра покупателем.',
        ];
        foreach ($leads as $slug => $lead) {
            $name = (string) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $slug)->value('name');
            if ($name === '') {
                continue;
            }
            $this->updateSectionData($tenantId, $slug, 'hero', [
                'variant' => 'full_background',
                'heading' => $name,
                'subheading' => $lead,
                'button_text' => 'Оставить заявку',
                'button_url' => '#expert-inquiry',
                'overlay_dark' => true,
            ], $force, $ifPlaceholder, $forceSection);
            $this->updateSectionData($tenantId, $slug, 'body', [
                'content' => '<p>'.e($lead).' Сроки и стоимость фиксируем после осмотра и согласования плана, без сюрпризов в процессе.</p>',
            ], $force, $ifPlaceholder, $forceSection);
        }
    }

    private function updateCases(
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
        $aI = htmlspecialchars(BlackDuckContentConstants::URL_INSTAGRAM, ENT_QUOTES, 'UTF-8');
        $this->ensureOrUpdateRichTextSection(
            $tenantId,
            'otzyvy',
            'external_social',
            10,
            'Ссылки на отзывы',
            '<p>Посмотреть отзывы и рейтинги в картосервисах: '
            .'<a href="'.$a2.'" rel="noopener">2ГИС</a> · '
            .'<a href="'.$aY.'" rel="noopener">Яндекс.Карты</a> · '
            .'<a href="'.$aI.'" rel="noopener">Instagram</a>'
            .'.</p>',
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
            'social_note' => 'Instagram: '.BlackDuckContentConstants::URL_INSTAGRAM,
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
            BlackDuckContentConstants::URL_INSTAGRAM,
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
            'uslugi' => ['Услуги — Black Duck Detailing', 'Карта направлений: мойка, PPF, керамика, винил, тонировка, химчистка, полировка, предпродажа.'],
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
