<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [];

        $urls[] = [
            'loc' => url('/'),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        $pages = Page::where('status', 'published')
            ->where('slug', '!=', 'home')
            ->get();

        foreach ($pages as $page) {
            $urls[] = [
                'loc' => url('/'.$page->slug),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $page->updated_at?->format('Y-m-d'),
            ];
        }

        $motorcycles = Motorcycle::where('show_in_catalog', true)
            ->where('status', 'available')
            ->get();

        foreach ($motorcycles as $moto) {
            $urls[] = [
                'loc' => route('motorcycle.show', $moto->slug),
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod' => $moto->updated_at?->format('Y-m-d'),
            ];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Charset' => 'UTF-8',
        ]);
    }
}
