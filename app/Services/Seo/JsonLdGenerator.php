<?php

namespace App\Services\Seo;

use App\Models\SeoMeta;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class JsonLdGenerator
{
    public function __construct(
        private TenantSeoJsonLdFactory $jsonLdFactory,
        private TenantSeoJsonLdOverrideMerger $overrideMerger,
    ) {}

    /**
     * @param  list<array{url: string, name: string}>|null  $itemListEntries
     * @return list<array<string, mixed>>
     */
    public function buildGraph(
        Tenant $tenant,
        string $routeName,
        ?Model $model,
        ?SeoMeta $seo,
        string $canonicalUrl,
        ?array $itemListEntries = null,
    ): array {
        $graph = $this->jsonLdFactory->buildBaseGraph(
            $tenant,
            $routeName,
            $model,
            $canonicalUrl,
            $itemListEntries,
        );

        return $this->overrideMerger->merge($graph, $seo);
    }

    /**
     * @return list<array{url: string, name: string}>
     */
    public function catalogItemEntries(Tenant $tenant, Collection $motorcycles): array
    {
        return $this->jsonLdFactory->catalogItemEntries($tenant, $motorcycles);
    }
}
