<?php

namespace App\Services\Seo;

use App\Models\LocationLandingPage;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\SeoLandingPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the primary Eloquent model for tenant SEO (no duplicate URL parsing).
 */
final class TenantSeoRequestModelResolver
{
    public function resolve(Request $request, string $routeName): ?Model
    {
        return match ($routeName) {
            'home' => Page::query()
                ->where('slug', 'home')
                ->where('status', 'published')
                ->first(),
            'faq' => $this->pageFromSlug('faq'),
            'contacts' => Page::query()
                ->where('slug', 'contacts')
                ->where('status', 'published')
                ->first(),
            'terms' => Page::query()
                ->where('slug', 'usloviya-arenda')
                ->where('status', 'published')
                ->first(),
            'about' => $this->pageFromSlug('about'),
            'page.show' => $this->pageFromSlug($this->slugForPageShow($request)),
            'motorcycle.show' => $this->motorcycleFromSlug($request->route('slug'), requireAvailable: true),
            'booking.show' => $this->motorcycleFromSlug($request->route('slug'), requireAvailable: false),
            'location.show' => $this->locationFromSlug($request->route('slug')),
            'seo_landing.show' => $this->seoLandingFromSlug($request->route('slug')),
            default => null,
        };
    }

    /**
     * The page.show `{slug}` param should be on the route; if it is missing, use a single root path segment.
     */
    private function slugForPageShow(Request $request): ?string
    {
        $slug = $request->route()?->parameter('slug');
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $path = trim((string) $request->path(), '/');
        if ($path !== '' && ! str_contains($path, '/')) {
            return $path;
        }

        return null;
    }

    private function pageFromSlug(mixed $slug): ?Page
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();
    }

    private function motorcycleFromSlug(mixed $slug, bool $requireAvailable): ?Motorcycle
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $q = Motorcycle::query()
            ->where('slug', $slug)
            ->where('show_in_catalog', true);
        if ($requireAvailable) {
            $q->where('status', 'available');
        }

        return $q->first();
    }

    private function locationFromSlug(mixed $slug): ?LocationLandingPage
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return LocationLandingPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    private function seoLandingFromSlug(mixed $slug): ?SeoLandingPage
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return SeoLandingPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }
}
