<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use Illuminate\Support\Collection;

/**
 * Публичный футер для темы {@code advocate_editorial}: ссылки из опубликованных CMS-страниц и фиксированных маршрутов,
 * тексты бренда и юр. дисклеймер — из {@see TenantSetting} (не из Blade).
 */
final class TenantAdvocateEditorialFooterData
{
    /** @var list<string> */
    private const PRACTICE_SLUGS_ORDERED = [
        'criminal-defense',
        'jury-trial',
        'civil-disputes',
        'arbitration',
        'migration',
    ];

    /** @var list<string> */
    private const LEGAL_SLUGS_ORDERED = [
        'privacy-policy',
        'consent-personal-data',
    ];

    /**
     * @return array{
     *   brand_title: string,
     *   brand_mark_url: string,
     *   brand_blurb: string,
     *   approach_line: string,
     *   nav_items: list<array{label: string, url: string}>,
     *   practice_items: list<array{label: string, url: string}>,
     *   legal_items: list<array{label: string, url: string}>,
     *   copyright_holder: string,
     *   disclaimer: string,
     *   office_address: string,
     *   year: int
     * }
     */
    public function build(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;
        $siteName = (string) TenantSetting::getForTenant($tenantId, 'general.site_name', $tenant->defaultPublicSiteName());

        $mark = $this->brandMarkPublicUrl($tenant);

        $blurb = trim((string) TenantSetting::getForTenant($tenantId, 'public_site.footer_brand_blurb', ''));
        $approach = trim((string) TenantSetting::getForTenant($tenantId, 'public_site.footer_approach_line', ''));
        $disclaimer = trim((string) TenantSetting::getForTenant($tenantId, 'public_site.footer_legal_disclaimer', ''));
        $office = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.public_office_address', ''));

        $pages = Page::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->whereIn('slug', array_merge(
                ['about', 'practice-areas', 'jury-trial'],
                self::PRACTICE_SLUGS_ORDERED,
                self::LEGAL_SLUGS_ORDERED,
            ))
            ->get()
            ->keyBy(static fn (Page $p): string => $p->slug);

        return [
            'brand_title' => $siteName,
            'brand_mark_url' => $mark,
            'brand_blurb' => $blurb,
            'approach_line' => $approach,
            'nav_items' => $this->navItems($pages),
            'practice_items' => $this->practiceItems($pages),
            'legal_items' => $this->legalItems($pages),
            'copyright_holder' => $siteName,
            'disclaimer' => $disclaimer,
            'office_address' => $office,
            'year' => (int) now()->year,
        ];
    }

    /**
     * @param  Collection<string, Page>  $pages
     * @return list<array{label: string, url: string}>
     */
    private function navItems(Collection $pages): array
    {
        $out = [];

        $out[] = ['label' => 'Главная', 'url' => route('home')];

        $about = $pages->get('about');
        if ($about instanceof Page) {
            $out[] = ['label' => $about->name, 'url' => route('page.show', ['slug' => 'about'])];
        }

        $practices = $pages->get('practice-areas');
        if ($practices instanceof Page) {
            $out[] = ['label' => $practices->name, 'url' => route('page.show', ['slug' => 'practice-areas'])];
        }

        $juryNav = $pages->get('jury-trial');
        if ($juryNav instanceof Page) {
            $out[] = ['label' => $juryNav->name, 'url' => route('page.show', ['slug' => 'jury-trial'])];
        }

        $out[] = ['label' => 'Вопросы и ответы', 'url' => route('faq')];
        $out[] = ['label' => 'Контакты', 'url' => route('contacts')];

        return $out;
    }

    /**
     * @param  Collection<string, Page>  $pages
     * @return list<array{label: string, url: string}>
     */
    private function practiceItems(Collection $pages): array
    {
        $out = [];
        foreach (self::PRACTICE_SLUGS_ORDERED as $slug) {
            $p = $pages->get($slug);
            if (! $p instanceof Page) {
                continue;
            }
            $out[] = ['label' => $p->name, 'url' => route('page.show', ['slug' => $slug])];
        }

        return $out;
    }

    /**
     * @param  Collection<string, Page>  $pages
     * @return list<array{label: string, url: string}>
     */
    private function legalItems(Collection $pages): array
    {
        $out = [];
        foreach (self::LEGAL_SLUGS_ORDERED as $slug) {
            $p = $pages->get($slug);
            if (! $p instanceof Page) {
                continue;
            }
            $out[] = ['label' => $p->name, 'url' => route('page.show', ['slug' => $slug])];
        }

        return $out;
    }

    private function brandMarkPublicUrl(Tenant $tenant): string
    {
        $tid = (int) $tenant->id;
        // Тот же источник, что и шапка ({@see tenant_branding_logo_url}): path + URL из TenantSetting.
        $fromSettings = trim((string) tenant_branding_asset_url(
            TenantSetting::getForTenant($tid, 'branding.logo_path', ''),
            TenantSetting::getForTenant($tid, 'branding.logo', ''),
        ));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        $ts = TenantStorage::for($tenant);
        foreach (['logo-mark.png', 'logo-mark-header.png'] as $basename) {
            $rel = 'site/brand/'.$basename;
            if ($ts->existsPublic($rel)) {
                return $ts->publicUrl($rel);
            }
        }

        return '';
    }
}
