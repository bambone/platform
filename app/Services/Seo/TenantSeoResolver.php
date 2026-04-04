<?php

namespace App\Services\Seo;

use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class TenantSeoResolver
{
    public function __construct(
        private SeoRouteRegistry $registry,
        private FallbackSeoGenerator $fallback,
        private TenantCanonicalPublicBaseUrl $canonicalBase,
        private JsonLdGenerator $jsonLd,
    ) {}

    /**
     * @param  array<string, mixed>  $context  e.g. item_list_entries for ItemList JSON-LD
     */
    public function resolve(Request $request, Tenant $tenant, string $routeName, ?Model $model = null, array $context = []): SeoResolvedData
    {
        $seo = null;
        if ($model !== null) {
            $model->loadMissing('seoMeta');
            $rel = $model->seoMeta;
            $seo = $rel instanceof SeoMeta ? $rel : null;
        }

        $siteName = $this->fallback->siteName($tenant);
        $vars = $this->interpolationVars($tenant, $routeName, $model, $siteName);

        $registryRow = $this->mergeTenantRouteOverrides($tenant, $routeName, $this->registry->get($routeName));
        $registryInterpolated = is_array($registryRow) ? $this->registry->interpolateRow($registryRow, $vars) : [];

        if ($routeName === 'page.show' && ($vars['page_name'] ?? '') === '') {
            unset($registryInterpolated['title'], $registryInterpolated['description'], $registryInterpolated['h1']);
        }

        $fb = $this->fallbackBundle($tenant, $routeName, $model);

        $title = (string) TenantSeoMerge::firstFilled(
            $seo?->meta_title,
            isset($registryInterpolated['title']) ? (string) $registryInterpolated['title'] : null,
            $fb['title'] !== '' ? $fb['title'] : null,
        );
        if ($title === '') {
            $title = $siteName !== '' ? $siteName : 'Rent';
        }

        $description = (string) TenantSeoMerge::firstFilled(
            $seo?->meta_description,
            isset($registryInterpolated['description']) ? (string) $registryInterpolated['description'] : null,
            $fb['description'] !== '' ? $fb['description'] : null,
        );

        $h1 = TenantSeoMerge::firstFilled(
            $seo?->h1,
            isset($registryInterpolated['h1']) ? (string) $registryInterpolated['h1'] : null,
            $fb['h1'] !== '' ? $fb['h1'] : null,
        );

        $canonical = $this->resolveCanonical($request, $tenant, $seo, $registryInterpolated);

        $robotsDirect = TenantSeoMerge::firstFilled(
            $seo?->robots,
            isset($registryInterpolated['robots']) ? (string) $registryInterpolated['robots'] : null,
            null,
        );

        $isIndexable = $seo?->is_indexable ?? true;
        $isFollowable = $seo?->is_followable ?? true;

        if ($robotsDirect !== null) {
            $robots = $robotsDirect;
        } else {
            $robots = ($isIndexable ? 'index' : 'noindex').', '.($isFollowable ? 'follow' : 'nofollow');
        }

        $ogTitle = (string) TenantSeoMerge::firstFilled($seo?->og_title, $title);
        $ogDescription = (string) TenantSeoMerge::firstFilled($seo?->og_description, $description);

        $ogImage = TenantSeoMerge::isFilled($seo?->og_image) ? trim((string) $seo->og_image) : null;
        $ogType = TenantSeoMerge::isFilled($seo?->og_type) ? trim((string) $seo->og_type) : 'website';
        $twitterCard = TenantSeoMerge::isFilled($seo?->twitter_card) ? trim((string) $seo->twitter_card) : 'summary_large_image';
        $metaKeywords = TenantSeoMerge::isFilled($seo?->meta_keywords) ? trim((string) $seo->meta_keywords) : null;

        $itemListEntries = isset($context['item_list_entries']) && is_array($context['item_list_entries'])
            ? $context['item_list_entries']
            : null;

        $jsonLdGraph = $this->jsonLd->buildGraph(
            $tenant,
            $routeName,
            $model,
            $seo,
            $canonical,
            $itemListEntries,
        );

        return new SeoResolvedData(
            title: $title,
            description: $description,
            h1: $h1,
            canonical: $canonical,
            ogTitle: $ogTitle,
            ogDescription: $ogDescription,
            ogUrl: $canonical,
            ogSiteName: $siteName,
            robots: $robots,
            jsonLd: $jsonLdGraph,
            metaKeywords: $metaKeywords,
            ogImage: $ogImage,
            ogType: $ogType,
            twitterCard: $twitterCard,
            isIndexable: $isIndexable,
            isFollowable: $isFollowable,
        );
    }

    /**
     * @return array{title: string, description: string, h1: string}
     */
    private function fallbackBundle(Tenant $tenant, string $routeName, ?Model $model): array
    {
        if ($model instanceof Page) {
            return $this->fallback->forPage($tenant, $model);
        }
        if ($model instanceof Motorcycle) {
            return $this->fallback->forMotorcycle($tenant, $model);
        }

        return $this->fallback->forRouteOnly($tenant, $routeName);
    }

    /**
     * @param  array<string, mixed>  $registryInterpolated
     */
    private function resolveCanonical(Request $request, Tenant $tenant, ?SeoMeta $seo, array $registryInterpolated): string
    {
        $fromMeta = $seo !== null && TenantSeoMerge::isFilled($seo->canonical_url) ? trim((string) $seo->canonical_url) : null;
        if ($fromMeta !== null && filter_var($fromMeta, FILTER_VALIDATE_URL)) {
            return $this->normalizeCanonicalUrlString($fromMeta);
        }

        $fromRegistry = isset($registryInterpolated['canonical']) && TenantSeoMerge::isFilled((string) $registryInterpolated['canonical'])
            ? trim((string) $registryInterpolated['canonical'])
            : null;
        if ($fromRegistry !== null && filter_var($fromRegistry, FILTER_VALIDATE_URL)) {
            return $this->normalizeCanonicalUrlString($fromRegistry);
        }

        return $this->defaultCanonicalForRequest($request, $tenant);
    }

    private function defaultCanonicalForRequest(Request $request, Tenant $tenant): string
    {
        $base = rtrim($this->canonicalBase->resolve($tenant), '/');
        $path = ltrim((string) $request->path(), '/');
        if ($path === '') {
            return $base.'/';
        }

        return $base.'/'.$path;
    }

    private function normalizeCanonicalUrlString(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }
        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        if ($path === '' || $path === '/') {
            $path = '/';
        }

        return $scheme.'://'.$host.$port.$path;
    }

    /**
     * @return array<string, string>
     */
    /**
     * Per-tenant patches over {@see config('seo_routes.routes')} (JSON in `tenant_settings` key `seo.route_overrides`).
     *
     * @param  array<string, mixed>|null  $base
     * @return array<string, mixed>|null
     */
    private function mergeTenantRouteOverrides(Tenant $tenant, string $routeName, ?array $base): ?array
    {
        $raw = TenantSetting::getForTenant($tenant->id, 'seo.route_overrides', '');
        if (! is_string($raw) || trim($raw) === '') {
            return $base;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $base;
        }

        $patch = $decoded[$routeName] ?? null;
        if (! is_array($patch)) {
            return $base;
        }

        $allowed = ['title', 'description', 'h1', 'canonical', 'robots'];
        $patch = array_intersect_key($patch, array_flip($allowed));
        if ($patch === []) {
            return $base;
        }

        if ($base === null) {
            return $patch;
        }

        return array_merge($base, $patch);
    }

    /**
     * @return array<string, string>
     */
    private function interpolationVars(Tenant $tenant, string $routeName, ?Model $model, string $siteName): array
    {
        $pageName = '';
        $motorcycleName = '';
        if ($model instanceof Page) {
            $pageName = trim((string) $model->name) ?: (string) $model->slug;
        }
        if ($model instanceof Motorcycle) {
            $motorcycleName = trim((string) $model->name) ?: (string) $model->slug;
        }

        return [
            'site_name' => $siteName,
            'page_name' => $pageName,
            'motorcycle_name' => $motorcycleName,
        ];
    }
}
