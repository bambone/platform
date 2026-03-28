<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Tenancy\TenantViewResolver;

class PageController extends Controller
{
    public function __construct(
        private readonly TenantViewResolver $tenantViews,
    ) {}

    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view($this->tenantViews->resolve('pages.page'), [
            'page' => $page,
            'seoMeta' => $page->seoMeta,
        ]);
    }
}
