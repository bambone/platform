<?php

namespace App\Services\Seo;

use App\Models\Faq;
use App\Models\LocationLandingPage;
use App\Models\Motorcycle;
use App\Models\SeoLandingPage;
use App\Models\Tenant;
use App\Support\Storage\TenantPublicAssetResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Base JSON-LD @graph (before {@see TenantSeoJsonLdOverrideMerger}); types limited to product whitelist.
 */
final class TenantSeoJsonLdFactory
{
    public function __construct(
        private TenantCanonicalPublicBaseUrl $canonicalBase,
        private FallbackSeoGenerator $fallback,
        private PublicBreadcrumbsBuilder $breadcrumbs,
        private TenantHomePublicFaqJsonLdEligibility $homeFaqEligibility,
    ) {}

    /**
     * @param  list<array{url: string, name: string}>|null  $itemListEntries
     * @return list<array<string, mixed>>
     */
    public function buildBaseGraph(
        Tenant $tenant,
        string $routeName,
        ?Model $model,
        string $canonicalUrl,
        ?array $itemListEntries = null,
    ): array {
        $base = rtrim($this->canonicalBase->resolve($tenant), '/');
        $siteUrl = $base.'/';

        if ($routeName === 'home') {
            return $this->homeGraph($tenant, $siteUrl);
        }

        if ($model instanceof Motorcycle && in_array($routeName, ['motorcycle.show', 'booking.show'], true)) {
            $product = $this->productFromMotorcycle($tenant, $model, $canonicalUrl);
            $crumbs = $this->breadcrumbs->forMotorcycle($tenant, $model);
            $out = [$product];
            $bc = $this->breadcrumbListFromCrumbs($crumbs);
            if ($bc !== null) {
                $out[] = $bc;
            }

            return $out;
        }

        if ($routeName === 'motorcycles.index' && is_array($itemListEntries) && $itemListEntries !== []) {
            return $this->catalogGraph($tenant, $canonicalUrl, $itemListEntries);
        }

        if ($routeName === 'faq') {
            $faqGraph = $this->faqPageGraph($tenant);
            if ($faqGraph !== []) {
                return $faqGraph;
            }
        }

        if ($model instanceof LocationLandingPage && $routeName === 'location.show') {
            return $this->locationLandingGraph($tenant, $model, $canonicalUrl);
        }

        if ($model instanceof SeoLandingPage && $routeName === 'seo_landing.show') {
            return $this->seoLandingGraph($tenant, $model, $canonicalUrl);
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function homeGraph(Tenant $tenant, string $siteUrl): array
    {
        $graph = $this->organizationAndWebSite($tenant, $siteUrl);
        $faqs = $this->homeFaqEligibility->eligiblePublishedFaqsForHome($tenant);
        if ($faqs !== null && $faqs->isNotEmpty()) {
            $faqBlock = $this->faqPageBlockFromFaqs($faqs);
            if ($faqBlock !== null) {
                $graph[] = $faqBlock;
            }
        }

        return $graph;
    }

    /**
     * @param  list<array{url: string, name: string}>  $itemListEntries
     * @return list<array<string, mixed>>
     */
    private function catalogGraph(Tenant $tenant, string $canonicalUrl, array $itemListEntries): array
    {
        $name = $this->fallback->siteName($tenant);
        $collection = [
            '@type' => 'CollectionPage',
            'url' => $canonicalUrl,
            'name' => 'Каталог мотоциклов'.($name !== '' ? ' — '.$name : ''),
        ];

        return [
            $collection,
            $this->itemList($itemListEntries),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function locationLandingGraph(Tenant $tenant, LocationLandingPage $page, string $canonicalUrl): array
    {
        $out = [
            [
                '@type' => 'WebPage',
                'url' => $canonicalUrl,
                'name' => trim((string) $page->title) ?: (string) $page->slug,
            ],
        ];
        $bc = $this->breadcrumbListFromCrumbs($this->breadcrumbs->forLocationLanding($tenant, $page));
        if ($bc !== null) {
            $out[] = $bc;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seoLandingGraph(Tenant $tenant, SeoLandingPage $page, string $canonicalUrl): array
    {
        $out = [
            [
                '@type' => 'WebPage',
                'url' => $canonicalUrl,
                'name' => trim((string) $page->title) ?: (string) $page->slug,
            ],
        ];
        $bc = $this->breadcrumbListFromCrumbs($this->breadcrumbs->forSeoLanding($tenant, $page));
        if ($bc !== null) {
            $out[] = $bc;
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     */
    private function breadcrumbListFromCrumbs(array $crumbs): ?array
    {
        if ($crumbs === []) {
            return null;
        }
        $elements = [];
        $pos = 1;
        foreach ($crumbs as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            $url = trim((string) ($c['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $name,
                'item' => $url,
            ];
        }

        if ($elements === []) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function organizationAndWebSite(Tenant $tenant, string $siteUrl): array
    {
        $name = $this->fallback->siteName($tenant);

        return [
            [
                '@type' => 'Organization',
                'name' => $name,
                'url' => $siteUrl,
            ],
            [
                '@type' => 'WebSite',
                'name' => $name,
                'url' => $siteUrl,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function faqPageGraph(Tenant $tenant): array
    {
        $max = (int) config('seo_autopilot.faq_json_ld_max_items', 20);
        if ($max < 1) {
            $max = 20;
        }

        $faqs = Faq::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($max)
            ->get(['question', 'answer']);

        $block = $this->faqPageBlockFromFaqs($faqs);

        return $block !== null ? [$block] : [];
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     */
    private function faqPageBlockFromFaqs(Collection $faqs): ?array
    {
        $mainEntity = [];
        foreach ($faqs as $faq) {
            $q = trim(strip_tags((string) $faq->question));
            $a = trim(strip_tags((string) $faq->answer));
            if ($q === '' || $a === '') {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }

        if ($mainEntity === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productFromMotorcycle(Tenant $tenant, Motorcycle $m, string $canonicalUrl): array
    {
        $base = rtrim($this->canonicalBase->resolve($tenant), '/');
        $name = trim((string) $m->name) ?: (string) $m->slug;
        $desc = '';
        if (TenantSeoMerge::isFilled($m->short_description)) {
            $desc = trim(strip_tags((string) $m->short_description));
        } elseif (TenantSeoMerge::isFilled($m->full_description)) {
            $desc = trim(strip_tags((string) $m->full_description));
        }
        if (mb_strlen($desc) > 5000) {
            $desc = mb_substr($desc, 0, 4997).'…';
        }

        $product = [
            '@type' => 'Product',
            'name' => $name,
            'url' => $canonicalUrl,
        ];
        if ($desc !== '') {
            $product['description'] = $desc;
        }
        if (TenantSeoMerge::isFilled($m->cover_url)) {
            $raw = trim((string) $m->cover_url);
            $resolved = TenantPublicAssetResolver::resolve($raw, (int) $tenant->id) ?? $raw;
            if (preg_match('#^https?://#i', $resolved) === 1) {
                $product['image'] = [$resolved];
            } else {
                $product['image'] = [$base.(str_starts_with($resolved, '/') ? $resolved : '/'.$resolved)];
            }
        }

        $brand = trim((string) $m->brand);
        if ($brand !== '') {
            $product['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }

        $m->loadMissing('category');
        if ($m->category !== null && TenantSeoMerge::isFilled($m->category->name)) {
            $product['category'] = (string) $m->category->name;
        }

        $slug = trim((string) $m->slug);
        if ($slug !== '') {
            $product['sku'] = $slug;
        }

        $additional = $this->motorcycleAdditionalPropertiesWhitelist($m);
        if ($additional !== []) {
            $product['additionalProperty'] = $additional;
        }

        $price = (int) ($m->price_per_day ?? 0);
        $currency = $this->resolveOfferCurrencyCode($tenant);
        if ($price > 0 && $currency !== null) {
            $availability = $this->schemaAvailabilityForMotorcycle($m);
            $offer = [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => (string) $price,
                'url' => $canonicalUrl,
            ];
            if ($availability !== null) {
                $offer['availability'] = $availability;
            }
            $product['offers'] = $offer;
        }

        return $product;
    }

    /**
     * Documented mapping only: public catalog + available → InStock; otherwise OutOfStock when listed in catalog.
     *
     * @return non-empty-string|null
     */
    private function schemaAvailabilityForMotorcycle(Motorcycle $m): ?string
    {
        if (! $m->show_in_catalog) {
            return null;
        }

        return $m->status === 'available'
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    /**
     * @return list<array<string, string>>
     */
    private function motorcycleAdditionalPropertiesWhitelist(Motorcycle $m): array
    {
        $out = [];
        if (filled($m->engine_cc)) {
            $out[] = [
                '@type' => 'PropertyValue',
                'name' => 'engineDisplacement',
                'value' => number_format((int) $m->engine_cc, 0, ',', ' ').' см³',
            ];
        }
        if (filled($m->power)) {
            $out[] = [
                '@type' => 'PropertyValue',
                'name' => 'power',
                'value' => (string) $m->power.' л.с.',
            ];
        }
        if (TenantSeoMerge::isFilled($m->transmission)) {
            $out[] = [
                '@type' => 'PropertyValue',
                'name' => 'transmission',
                'value' => (string) $m->transmission,
            ];
        }
        if (filled($m->year)) {
            $out[] = [
                '@type' => 'PropertyValue',
                'name' => 'modelYear',
                'value' => (string) $m->year,
            ];
        }

        return $out;
    }

    private function resolveOfferCurrencyCode(Tenant $tenant): ?string
    {
        $currency = strtoupper(trim((string) $tenant->currency));
        if (strlen($currency) === 3 && ctype_alpha($currency)) {
            return $currency;
        }

        return null;
    }

    /**
     * @param  list<array{url: string, name: string}>  $entries
     * @return array<string, mixed>
     */
    private function itemList(array $entries): array
    {
        $elements = [];
        $pos = 1;
        foreach ($entries as $e) {
            $url = $e['url'] ?? '';
            $name = $e['name'] ?? '';
            if ($url === '' || $name === '') {
                continue;
            }
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'item' => [
                    '@type' => 'Product',
                    'name' => $name,
                    'url' => $url,
                ],
            ];
        }

        return [
            '@type' => 'ItemList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return list<array{url: string, name: string}>
     */
    public function catalogItemEntries(Tenant $tenant, Collection $motorcycles): array
    {
        $base = rtrim($this->canonicalBase->resolve($tenant), '/');
        $out = [];
        foreach ($motorcycles as $m) {
            if (! $m instanceof Motorcycle) {
                continue;
            }
            $slug = trim((string) $m->slug);
            if ($slug === '') {
                continue;
            }
            $out[] = [
                'url' => $base.'/moto/'.rawurlencode($slug),
                'name' => trim((string) $m->name) ?: $slug,
            ];
        }

        return $out;
    }
}
