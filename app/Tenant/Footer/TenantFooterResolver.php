<?php

declare(strict_types=1);

namespace App\Tenant\Footer;

use App\ContactChannels\TenantPublicSiteContactsService;
use App\Models\Tenant;
use App\Models\TenantFooterLink;
use App\Models\TenantFooterSection;
use App\Models\TenantSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Публичный резолвер подвала: валидация meta_json, группировка ссылок, минимальный подвал при пустоте.
 * Не зависит от темы оформления.
 *
 * Источники данных (контракт):
 * — typed-секции БД ({@see TenantFooterSection}) — основной сценарий full mode;
 * — {@see TenantSetting}: имя сайта, подпись подвала, адрес для блока контактов — через {@see TenantFooterContactPresentation};
 * — minimal mode: системные ссылки только относительными путями (без route() с чужим host), плюс presentation и fallback-тексты.
 *
 * Режим full возвращается только если после валидации meta осталась хотя бы одна enabled-секция {@see TenantFooterSection}.
 * Иначе — minimal (например, пустая таблица секций, все секции отключены или с невалидным meta). Сидер MotoLevins наполняет секции
 * только при прогоне seed; у уже существующего tenant без backfill в БД будет minimal.
 */
final class TenantFooterResolver
{
    private const DEFAULT_MINIMAL_SERVICE_NOTE = 'Бронирование подтверждается оператором. Условия и детали согласуются перед выдачей.';

    private const DEFAULT_MINIMAL_BOOKING_SUBLINE = 'Онлайн-заявка не подтверждает бронь без ответа оператора: даты и детали согласуем в переписке или по телефону.';

    public function __construct(
        private readonly TenantFooterRepository $repository,
        private readonly FooterSectionMetaValidator $metaValidator,
        private readonly TenantPublicSiteContactsService $publicContacts,
    ) {}

    /**
     * @return array{
     *   mode: 'full'|'minimal',
     *   sections: list<array<string, mixed>>,
     *   site_name: string,
     *   year: int,
     *   footer_tagline: string,
     *   minimal_service_note: string,
     *   contact_presentation: array<string, string>,
     *   system_links: list<array{label: string, url: string}>,
     *   minimal_booking_subline?: string,
     *   expert_pr_footer?: bool,
     * }
     */
    public function resolve(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;
        $siteName = (string) TenantSetting::getForTenant($tenantId, 'general.site_name', $tenant->defaultPublicSiteName());
        $year = (int) now()->year;
        $footerTagline = trim((string) TenantSetting::getForTenant($tenantId, 'general.footer_tagline', ''));
        $presentation = TenantFooterContactPresentation::forTenant($tenant, $this->publicContacts);

        $sectionsOut = [];
        foreach ($this->repository->enabledSectionsWithLinks($tenantId) as $section) {
            $built = $this->buildSectionBlock($section);
            if ($built === null) {
                continue;
            }
            if (($built['type'] ?? '') === FooterSectionType::CONTACTS) {
                $built['presentation'] = $presentation;
            }
            $sectionsOut[] = $built;
        }

        if ($sectionsOut === []) {
            return $this->minimalFooter(
                $tenant,
                $siteName,
                $year,
                $footerTagline,
                $this->presentationForMinimalFooterStrip($tenant, $presentation),
            );
        }

        return [
            'mode' => 'full',
            'sections' => $sectionsOut,
            'site_name' => $siteName,
            'year' => $year,
            'footer_tagline' => $footerTagline,
            'minimal_service_note' => self::DEFAULT_MINIMAL_SERVICE_NOTE,
            'contact_presentation' => $presentation,
            'system_links' => [],
        ];
    }

    /**
     * В minimal подвале мессенджеры управляются {@see TenantPublicSiteContactsService::footerMessengerLinksEnabled}
     * (по умолчанию совпадает с FAB; можно развести в настройках).
     * Full mode, секция «Контакты», использует полный {@see TenantFooterContactPresentation}.
     *
     * @param  array<string, string>  $presentation
     * @return array<string, string>
     */
    private function presentationForMinimalFooterStrip(Tenant $tenant, array $presentation): array
    {
        if ($this->publicContacts->footerMessengerLinksEnabled((int) $tenant->id)) {
            return $presentation;
        }

        return array_merge($presentation, [
            'telegram_handle' => '',
            'telegram_display' => '',
            'telegram_url' => '',
            'whatsapp_digits' => '',
            'whatsapp_url' => '',
            'vk_url' => '',
        ]);
    }

    /**
     * @param  array<string, string>  $presentation
     * @return array{
     *   mode: 'minimal',
     *   sections: list<array<string, mixed>>,
     *   site_name: string,
     *   year: int,
     *   footer_tagline: string,
     *   minimal_service_note: string,
     *   minimal_booking_subline: string,
     *   contact_presentation: array<string, string>,
     *   system_links: list<array{label: string, url: string}>,
     *   expert_pr_footer: bool,
     * }
     */
    private function minimalFooter(
        Tenant $tenant,
        string $siteName,
        int $year,
        string $footerTagline,
        array $presentation,
    ): array {
        $systemLinks = [];
        /** @var list<array{route: string, path: string, label: string}> $candidates Относительные пути — корректны на любом домене тенанта (без route() в сидере/CLI). */
        if ($tenant->themeKey() === 'black_duck') {
            $candidates = [
                ['route' => 'contacts', 'path' => '/contacts', 'label' => 'Контакты'],
                ['route' => 'privacy', 'path' => '/politika-konfidencialnosti', 'label' => 'Политика конфиденциальности'],
            ];
        } elseif ($tenant->themeKey() === 'expert_pr') {
            /** Slug-страницы Magas (/privacy, /terms), не мото-маршруты usloviya/politika. */
            $candidates = [
                ['route' => 'contacts', 'path' => '/contacts', 'label' => 'Contacts'],
                ['route' => 'page.show', 'path' => '/privacy', 'label' => 'Privacy'],
                ['route' => 'page.show', 'path' => '/terms', 'label' => 'Terms'],
            ];
        } else {
            $candidates = [
                ['route' => 'contacts', 'path' => '/contacts', 'label' => 'Контакты'],
                ['route' => 'terms', 'path' => '/usloviya-arenda', 'label' => 'Правила аренды'],
                ['route' => 'privacy', 'path' => '/politika-konfidencialnosti', 'label' => 'Политика конфиденциальности'],
            ];
        }
        foreach ($candidates as $c) {
            if (! Route::has($c['route'])) {
                continue;
            }
            $systemLinks[] = [
                'label' => $c['label'],
                'url' => $c['path'],
            ];
            if (count($systemLinks) >= 5) {
                break;
            }
        }

        $isExpertPrMinimal = $tenant->themeKey() === 'expert_pr';

        return [
            'mode' => 'minimal',
            'sections' => [],
            'site_name' => $siteName,
            'year' => $year,
            'footer_tagline' => $footerTagline,
            'minimal_service_note' => $isExpertPrMinimal
                ? 'Use the contact brief to outline goals and constraints — we clarify scope before committing to timelines.'
                : self::DEFAULT_MINIMAL_SERVICE_NOTE,
            'minimal_booking_subline' => $isExpertPrMinimal
                ? 'Inbound replies typically within one business day for qualified B2B enquiries; escalation paths exist for reputational urgency.'
                : self::DEFAULT_MINIMAL_BOOKING_SUBLINE,
            'contact_presentation' => $presentation,
            'system_links' => $systemLinks,
            'expert_pr_footer' => $isExpertPrMinimal,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSectionBlock(TenantFooterSection $section): ?array
    {
        $type = (string) $section->type;
        /** @var array<string, mixed> $meta */
        $meta = is_array($section->meta_json) ? $section->meta_json : [];

        $validated = $this->metaValidator->validate($type, $meta);
        if (! $validated['ok']) {
            Log::warning('tenant_footer_section_meta_invalid', [
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
                'type' => $type,
                'message' => $validated['message'] ?? 'invalid',
            ]);

            return null;
        }

        $cleanMeta = $validated['meta'];
        if (! $this->metaValidator->hasMinimumContentForEnabled($type, $cleanMeta)) {
            Log::warning('tenant_footer_section_below_minimum', [
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
                'type' => $type,
            ]);

            return null;
        }

        if ($type === FooterSectionType::LINK_GROUPS) {
            $grouped = $this->groupLinksForLinkGroupsSection($section, $cleanMeta);
            if ($grouped === null) {
                return null;
            }

            return [
                'type' => $type,
                'title' => $section->title,
                'body' => $section->body,
                'meta' => $cleanMeta,
                'link_groups' => $grouped,
            ];
        }

        return [
            'type' => $type,
            'title' => $section->title,
            'body' => $section->body,
            'meta' => $cleanMeta,
        ];
    }

    /**
     * @param  array<string, mixed>  $cleanMeta
     * @return list<array{group_key: string, title: string, links: list<array{label: string, href: string, target: string}>}>|null
     */
    private function groupLinksForLinkGroupsSection(TenantFooterSection $section, array $cleanMeta): ?array
    {
        $links = $section->links;
        if ($links->isEmpty()) {
            Log::warning('tenant_footer_link_groups_empty', [
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
            ]);

            return null;
        }

        if ($links->count() > FooterLimits::MAX_LINKS_PER_SECTION) {
            Log::warning('tenant_footer_link_groups_too_many_links', [
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
            ]);

            return null;
        }

        /** @var array<string, string> $groupTitles */
        $groupTitles = is_array($cleanMeta['group_titles'] ?? null)
            ? $cleanMeta['group_titles']
            : [];

        /** @var array<string, Collection<int, TenantFooterLink>> $byGroup */
        $byGroup = [];
        foreach ($links as $link) {
            $gk = (string) ($link->group_key ?? '');
            if ($gk === '') {
                $gk = 'default';
            }
            if (! isset($byGroup[$gk])) {
                $byGroup[$gk] = collect();
            }
            $byGroup[$gk]->push($link);
        }

        if (count($byGroup) > FooterLimits::LINK_GROUPS_MAX_GROUPS) {
            Log::warning('tenant_footer_link_groups_too_many_groups', [
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
            ]);

            return null;
        }

        $out = [];
        foreach ($byGroup as $gk => $groupLinks) {
            if ($groupLinks->count() > FooterLimits::LINK_GROUP_MAX_LINKS) {
                Log::warning('tenant_footer_link_group_too_many', [
                    'tenant_id' => $section->tenant_id,
                    'section_id' => $section->id,
                    'group_key' => $gk,
                ]);

                return null;
            }

            $linkRows = [];
            foreach ($groupLinks as $link) {
                $linkRows[] = [
                    'label' => (string) $link->label,
                    'href' => TenantFooterLinkPresenter::href((string) $link->url, $link->link_kind),
                    'target' => TenantFooterLinkPresenter::defaultTarget($link->link_kind, $link->target),
                ];
            }

            $title = $groupTitles[$gk] ?? $gk;
            if (! is_string($title) || $title === '') {
                $title = $gk === 'default' ? 'Ссылки' : $gk;
            }

            $out[] = [
                'group_key' => $gk,
                'title' => $title,
                'links' => $linkRows,
            ];
        }

        return $out;
    }
}
