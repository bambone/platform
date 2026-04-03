<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Review;
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

    public function index()
    {
        $bikes = Motorcycle::where('show_in_catalog', true)
            ->where('status', 'available')
            ->with(['category', 'media'])
            ->orderBy('sort_order')
            ->get();
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

        $homeLayoutSections = $this->getHomeLayoutSections();
        $sections = $this->sectionsKeyedForLegacyComponents($homeLayoutSections);
        $page = Page::where('slug', 'home')->where('status', 'published')->first();
        $seoMeta = $page?->seoMeta;
        $faqs = Faq::where('show_on_home', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get();
        $reviews = $this->getHomeReviews($homeLayoutSections);

        return tenant_view('pages.home', compact(
            'bikes',
            'badges',
            'sections',
            'homeLayoutSections',
            'faqs',
            'reviews',
            'seoMeta',
        ));
    }

    /**
     * @return BaseCollection<int, PageSection>
     */
    private function getHomeLayoutSections(): BaseCollection
    {
        $page = Page::where('slug', 'home')->where('status', 'published')->first();
        if (! $page) {
            return collect();
        }

        return $page->sections()
            ->where('status', 'published')
            ->where('is_visible', true)
            ->where('section_key', '!=', 'main')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
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
