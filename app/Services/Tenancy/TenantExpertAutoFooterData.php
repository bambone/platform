<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantSetting;

/**
 * Публичный футер для темы {@code expert_auto}: навигация из CMS-меню, контакты из view composer,
 * адрес офиса / зона выезда — {@see TenantSetting} {@code contacts.public_office_address}.
 */
final class TenantExpertAutoFooterData
{
    public function __construct(
        private readonly TenantMainMenuPages $mainMenu,
    ) {}

    /**
     * @return array{
     *   nav_items: list<array{label: string, url: string}>,
     *   office_address: string,
     *   copyright_holder: string,
     *   year: int
     * }
     */
    public function build(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;
        $siteName = (string) TenantSetting::getForTenant($tenantId, 'general.site_name', $tenant->defaultPublicSiteName());
        $office = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.public_office_address', ''));

        $nav = $this->mainMenu->menuItems($tenant)->all();
        array_unshift($nav, ['label' => 'Главная', 'url' => route('home')]);

        return [
            'nav_items' => $nav,
            'office_address' => $office,
            'copyright_holder' => $siteName,
            'year' => (int) now()->year,
        ];
    }
}
