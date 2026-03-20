<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\Review;
use Illuminate\Database\Eloquent\Collection;

class HomeController extends Controller
{
    public function index()
    {
        $bikes = Motorcycle::where('show_in_catalog', true)
            ->where('status', 'available')
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

        $sections = $this->getHomeSections();
        $page = Page::where('slug', 'home')->where('status', 'published')->first();
        $seoMeta = $page?->seoMeta;
        $faqs = Faq::where('show_on_home', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get();
        $reviews = $this->getHomeReviews($sections['reviews_block'] ?? []);

        return view('pages.home', compact('bikes', 'badges', 'sections', 'faqs', 'reviews', 'seoMeta'));
    }

    private function getHomeReviews(array $section): Collection
    {
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

    private function getHomeSections(): array
    {
        $page = Page::where('slug', 'home')->where('status', 'published')->first();
        if (! $page) {
            return [];
        }

        return $page->sections()
            ->where('status', 'published')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('section_key')
            ->map(fn ($s) => $s->data_json ?? [])
            ->all();
    }
}
