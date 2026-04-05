<?php

namespace App\Services\Seo;

use App\Models\Faq;
use App\Models\Motorcycle;
use App\Models\Tenant;
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
            return $this->organizationAndWebSite($tenant, $siteUrl);
        }

        if ($model instanceof Motorcycle && in_array($routeName, ['motorcycle.show', 'booking.show'], true)) {
            return [$this->productFromMotorcycle($tenant, $model, $canonicalUrl)];
        }

        if ($routeName === 'motorcycles.index' && is_array($itemListEntries) && $itemListEntries !== []) {
            return [$this->itemList($itemListEntries)];
        }

        if ($routeName === 'faq') {
            $faqGraph = $this->faqPageGraph($tenant);
            if ($faqGraph !== []) {
                return $faqGraph;
            }
        }

        return [];
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

        if ($faqs->isEmpty()) {
            return [];
        }

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
            return [];
        }

        return [
            [
                '@type' => 'FAQPage',
                'mainEntity' => $mainEntity,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productFromMotorcycle(Tenant $tenant, Motorcycle $m, string $canonicalUrl): array
    {
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
            $product['image'] = [(string) $m->cover_url];
        }

        $price = (int) ($m->price_per_day ?? 0);
        $currency = $this->resolveOfferCurrencyCode($tenant);
        if ($price > 0 && $currency !== null) {
            $product['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => (string) $price,
                'url' => $canonicalUrl,
            ];
        }

        return $product;
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
