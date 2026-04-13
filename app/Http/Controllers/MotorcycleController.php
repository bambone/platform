<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\TenantSetting;
use App\Services\Catalog\MotorcycleLocationCatalogService;
use App\Services\Catalog\TenantPublicCatalogLocationService;
use App\Services\Seo\CatalogPublicIntroResolver;
use App\Services\Seo\PublicBreadcrumbsBuilder;
use App\Services\Seo\RelatedMotorcyclesService;
use App\Support\RussianPhone;

class MotorcycleController extends Controller
{
    /**
     * Публичный каталог мотоциклов (отдельный URL /motorcycles): тот же парк, что на главной, без фильтров по датам.
     */
    public function catalogIndex(
        TenantPublicCatalogLocationService $catalogLocation,
        MotorcycleLocationCatalogService $locationScope,
    ) {
        abort_if(tenant() === null, 404);

        $bikesQuery = Motorcycle::query()
            ->where('show_in_catalog', true)
            ->where('status', 'available')
            ->with(['category', 'media']);
        $selectedCatalogLocation = $catalogLocation->resolve();
        if ($selectedCatalogLocation !== null) {
            $locationScope->scopeMotorcyclesVisibleAtLocation($bikesQuery, $selectedCatalogLocation);
        }
        $bikes = $bikesQuery->orderBy('sort_order')->get();

        $badges = [
            'Хит',
            'Новинка',
            null,
            'Лучший выбор',
            null,
            'Новинка',
            null,
            'Лучший выбор',
        ];

        $tenant = tenant();
        $catalogIntroSection = $tenant !== null
            ? app(CatalogPublicIntroResolver::class)->resolveSection($tenant)
            : null;

        return tenant_view('pages.motorcycles.index', [
            'bikes' => $bikes,
            'badges' => $badges,
            'catalogIntroSection' => $catalogIntroSection,
            'catalogLocations' => $catalogLocation->activeLocationsForCurrentTenant(),
            'selectedCatalogLocation' => $selectedCatalogLocation,
            'catalogLocationFormAction' => route('motorcycles.index'),
        ]);
    }

    public function show(
        string $slug,
        TenantPublicCatalogLocationService $catalogLocation,
        MotorcycleLocationCatalogService $locationScope,
    ) {
        $motorcycle = Motorcycle::where('slug', $slug)
            ->where('show_in_catalog', true)
            ->with(['category', 'media'])
            ->firstOrFail();

        $selectedCatalogLocation = $catalogLocation->resolve();
        $visibleAtSelectedLocation = $selectedCatalogLocation === null
            || $locationScope->isMotorcycleVisibleAtLocation($motorcycle, $selectedCatalogLocation);

        $galleryUrls = [];
        if ($motorcycle->cover_url) {
            $galleryUrls[] = $motorcycle->publicMediaDisplayUrl($motorcycle->cover_url);
        }
        foreach ($motorcycle->getMedia('gallery') as $media) {
            $galleryUrls[] = $motorcycle->publicMediaDisplayUrl($media->getUrl());
        }
        $galleryUrls = array_values(array_unique($galleryUrls));

        $related = app(RelatedMotorcyclesService::class)->forMotorcycle($motorcycle, 3);

        $tenant = tenant();
        $breadcrumbs = $tenant !== null
            ? app(PublicBreadcrumbsBuilder::class)->forMotorcycle($tenant, $motorcycle)
            : [];

        $tenantId = $motorcycle->tenant_id;
        $contactPhoneRaw = TenantSetting::getForTenant($tenantId, 'contacts.phone', '');
        $contactEmail = TenantSetting::getForTenant($tenantId, 'contacts.email', '');

        return tenant_view('pages.motorcycle', [
            'motorcycle' => $motorcycle,
            'galleryUrls' => $galleryUrls,
            'relatedMotorcycles' => $related,
            'publicBreadcrumbs' => $breadcrumbs,
            'specGroups' => $motorcycle->specGroupsForPublic(),
            'detailContent' => $motorcycle->detailContentForView(),
            'contactTelHref' => RussianPhone::normalize($contactPhoneRaw),
            'contactEmail' => trim((string) $contactEmail),
            'catalogLocations' => $catalogLocation->activeLocationsForCurrentTenant(),
            'selectedCatalogLocation' => $selectedCatalogLocation,
            'catalogLocationFormAction' => route('motorcycles.index'),
            'visibleAtSelectedLocation' => $visibleAtSelectedLocation,
        ]);
    }
}
