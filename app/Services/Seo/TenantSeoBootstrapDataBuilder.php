<?php

namespace App\Services\Seo;

use App\Models\Faq;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Seo\Data\TenantSeoBootstrapData;

final class TenantSeoBootstrapDataBuilder
{
    public function __construct(
        private FallbackSeoGenerator $fallback,
        private TenantCanonicalPublicBaseUrl $canonicalBase,
    ) {}

    public function build(Tenant $tenant): TenantSeoBootstrapData
    {
        $tenant->loadMissing('domainLocalizationPreset');

        $currency = strtoupper(trim((string) $tenant->currency));
        $hasReliableCurrency = strlen($currency) === 3 && ctype_alpha($currency);

        $home = Page::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'home')
            ->where('status', 'published')
            ->first();

        $catalogCount = Motorcycle::query()
            ->where('tenant_id', $tenant->id)
            ->where('show_in_catalog', true)
            ->where('status', 'available')
            ->count();

        $og = $this->resolveRepresentativeOgImageUrl($tenant);

        return new TenantSeoBootstrapData(
            tenantId: $tenant->id,
            siteName: $this->fallback->siteName($tenant),
            primaryPublicBaseUrl: rtrim($this->canonicalBase->resolve($tenant), '/'),
            themeKey: $tenant->theme_key,
            localizationPresetSlug: $tenant->domainLocalizationPreset?->slug,
            locale: (string) ($tenant->locale ?: 'ru'),
            tenantCurrency: $currency,
            hasReliableTenantCurrency: $hasReliableCurrency,
            hasPublishedHomePage: $home !== null,
            catalogItemsCount: $catalogCount,
            faqPublishedCount: (int) Faq::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'published')
                ->count(),
            hasContactPhone: TenantSeoMerge::isFilled((string) TenantSetting::getForTenant($tenant->id, 'contacts.phone', '')),
            hasContactEmail: TenantSeoMerge::isFilled((string) TenantSetting::getForTenant($tenant->id, 'contacts.email', '')),
            representativeOgImageUrl: $og,
        );
    }

    private function resolveRepresentativeOgImageUrl(Tenant $tenant): ?string
    {
        $logo = trim((string) TenantSetting::getForTenant($tenant->id, 'branding.logo', ''));
        if ($logo !== '' && filter_var($logo, FILTER_VALIDATE_URL)) {
            return $logo;
        }

        $m = Motorcycle::query()
            ->where('tenant_id', $tenant->id)
            ->where('show_in_catalog', true)
            ->where('status', 'available')
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'cover'))
            ->orderBy('sort_order')
            ->first();

        if ($m !== null && TenantSeoMerge::isFilled($m->cover_url)) {
            $u = trim((string) $m->cover_url);

            return filter_var($u, FILTER_VALIDATE_URL) ? $u : null;
        }

        return null;
    }
}
