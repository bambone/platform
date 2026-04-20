<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Collection;

/**
 * Публичный подвал мото-сайта: навигация из CMS-меню, контакты из view composer,
 * адрес / зона выезда — {@code contacts.public_office_address}, иначе {@code contacts.address} (как в настройках сайта);
 * юридический ряд — опубликованные CMS-страницы по {@see self::LEGAL_SLUGS_ORDERED};
 * подпись под копирайтом — {@code general.footer_tagline} (иначе дефолт для проката).
 *
 * Используется для мото-публичных тем: {@code default}, {@code moto} (bundled), {@code expert_auto} (см. {@see \App\Providers\AppServiceProvider}).
 */
final class TenantExpertAutoFooterData
{
    /** @var list<string> */
    private const LEGAL_SLUGS_ORDERED = [
        'usloviya-arenda',
        'politika-konfidencialnosti',
    ];

    public function __construct(
        private readonly TenantMainMenuPages $mainMenu,
    ) {}

    private const DEFAULT_FOOTER_TAGLINE = 'Аренда и сопровождение: согласуем даты, условия и детали по телефону или в мессенджере.';

    /**
     * @return array{
     *   nav_items: list<array{label: string, url: string}>,
     *   legal_items: list<array{label: string, url: string}>,
     *   office_address: string,
     *   copyright_holder: string,
     *   year: int,
     *   footer_tagline: string
     * }
     */
    public function build(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;
        $siteName = (string) TenantSetting::getForTenant($tenantId, 'general.site_name', $tenant->defaultPublicSiteName());
        $office = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.public_office_address', ''));
        if ($office === '') {
            $office = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.address', ''));
        }
        $tagline = trim((string) TenantSetting::getForTenant($tenantId, 'general.footer_tagline', ''));
        if ($tagline === '') {
            $tagline = self::DEFAULT_FOOTER_TAGLINE;
        }

        $nav = $this->mainMenu->menuItems($tenant)->all();
        array_unshift($nav, ['label' => 'Главная', 'url' => route('home')]);

        $pages = Page::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->whereIn('slug', self::LEGAL_SLUGS_ORDERED)
            ->get()
            ->keyBy(static fn (Page $p): string => $p->slug);

        return [
            'nav_items' => $nav,
            'legal_items' => $this->legalItems($pages),
            'office_address' => $office,
            'copyright_holder' => $siteName,
            'year' => (int) now()->year,
            'footer_tagline' => $tagline,
        ];
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
            $url = match ($slug) {
                'usloviya-arenda' => route('terms'),
                'politika-konfidencialnosti' => route('privacy'),
                default => route('page.show', ['slug' => $slug]),
            };
            $out[] = ['label' => $p->name, 'url' => $url];
        }

        return $out;
    }
}
