<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\CustomPageResolver;
use Illuminate\Contracts\View\View;

/**
 * /about: если у тенанта есть опубликованная страница со slug {@see Page::slug} = about — рендер через конструктор;
 * иначе — legacy-шаблон {@see tenant_view('pages.about')} (прокат и др.).
 */
final class AboutPageController extends Controller
{
    public function __construct(
        private readonly CustomPageResolver $resolver
    ) {}

    public function show(): View
    {
        $page = Page::query()
            ->where('slug', 'about')
            ->where('status', 'published')
            ->first();

        if ($page !== null) {
            $viewName = $this->resolver->resolveView($page->slug);

            return tenant_view($viewName, [
                'page' => $page,
            ]);
        }

        return tenant_view('pages.about');
    }
}
