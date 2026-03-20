<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('pages.page', [
            'page' => $page,
            'seoMeta' => $page->seoMeta,
        ]);
    }
}
