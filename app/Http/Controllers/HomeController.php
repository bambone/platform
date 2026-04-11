<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Review;
use App\Services\Catalog\MotorcycleLocationCatalogService;
use App\Services\Catalog\TenantPublicCatalogLocationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public static function forgetCachedPayloadForTenant(int $tenantId): void
    {
        Cache::forget(sprintf('tenant:%d:home:index:v2', $tenantId));
        Cache::forget(sprintf('tenant:%d:home:index:v1', $tenantId));
    }

    public function index(
        TenantPublicCatalogLocationService $catalogLocation,
        MotorcycleLocationCatalogService $locationScope,
    ) {
        $tenant = tenant();
        if ($tenant === null) {
            abort(404);
        }

        /*
         * Не кэшируем массив для Blade через Cache::remember: внутри Eloquent Collection / Model.
         * После serialize/unserialize из Redis на проде возможен «incomplete object» и 500 на home.blade.php.
         */
        if ($tenant->themeKey() === 'expert_auto') {
            return tenant_view('pages.home', $this->buildExpertAutoHomeIndexData());
        }

        return tenant_view('pages.home', $this->buildHomeIndexData($catalogLocation, $locationScope));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExpertAutoHomeIndexData(): array
    {
        $page = Page::query()
            ->where('slug', 'home')
            ->where('status', 'published')
            ->with(['sections' => function ($q): void {
                $q->where('status', 'published')
                    ->where('is_visible', true)
                    ->where('section_key', '!=', 'main')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->first();

        $homeLayoutSections = $page ? $page->sections : collect();
        $sections = $this->sectionsKeyedForLegacyComponents($homeLayoutSections);
        $faqs = Faq::where('show_on_home', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get();

        return [
            'bikes' => collect(),
            'badges' => [],
            'sections' => $sections,
            'homeLayoutSections' => $homeLayoutSections,
            'faqs' => $faqs,
            'reviews' => collect(),
            'selectedCatalogLocation' => null,
            'catalogLocations' => collect(),
            'catalogLocationFormAction' => route('home'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function buildHomeIndexData(
        TenantPublicCatalogLocationService $catalogLocation,
        MotorcycleLocationCatalogService $locationScope,
    ): array {
        $bikesQuery = Motorcycle::where('show_in_catalog', true)
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

        $page = Page::query()
            ->where('slug', 'home')
            ->where('status', 'published')
            ->with(['sections' => function ($q): void {
                $q->where('status', 'published')
                    ->where('is_visible', true)
                    ->where('section_key', '!=', 'main')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->first();

        $homeLayoutSections = $page ? $page->sections : collect();
        $sections = $this->sectionsKeyedForLegacyComponents($homeLayoutSections);
        $faqs = Faq::where('show_on_home', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get();
        $reviews = $this->getHomeReviews($homeLayoutSections);

        $catalogLocations = $catalogLocation->activeLocationsForCurrentTenant();
        $catalogLocationFormAction = route('home');

        return compact(
            'bikes',
            'badges',
            'sections',
            'homeLayoutSections',
            'faqs',
            'reviews',
            'selectedCatalogLocation',
            'catalogLocations',
            'catalogLocationFormAction',
        );
    }

    /**
     * Ключи как у старых фиксированных слотов (последняя секция с таким key перезаписывает при дублях).
     *
     * @param  BaseCollection<int, PageSection>  $layout
     * @return array<string, mixed>
     */
    private function sectionsKeyedForLegacyComponents(BaseCollection $layout): array
    {
        return $layout->keyBy('section_key')->map(fn ($s) => $s->data_json ?? [])->all();
    }

    /**
     * @param  BaseCollection<int, PageSection>  $layout
     */
    private function getHomeReviews(BaseCollection $layout): Collection
    {
        $block = $layout->firstWhere('section_key', 'reviews_block');
        $section = is_array($block?->data_json) ? $block->data_json : [];
        $ids = $section['selected_review_ids'] ?? [];
        if (! empty($ids)) {
            $ids = array_map('intval', $ids);
            $reviews = Review::whereIn('id', $ids)
                ->where('status', 'published')
                ->get();

            return $reviews->sortBy(fn ($r) => array_search($r->id, $ids))->values();
        }

        return Review::where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->limit(3)
            ->get();
    }
}
