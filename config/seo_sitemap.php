<?php

use App\Services\Seo\SitemapGenerator;
use App\Services\Seo\SitemapUrlProvider;

/**
 * Static public paths for tenant sitemap (canonical base is prepended by {@see SitemapUrlProvider}).
 *
 * @see SitemapGenerator
 */
return [
    'static_paths' => [
        ['path' => '/', 'changefreq' => 'daily', 'priority' => '1.0'],
        ['path' => '/contacts', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/faq', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/about', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/motorcycles', 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['path' => '/prices', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/order', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => '/reviews', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['path' => '/usloviya-arenda', 'changefreq' => 'yearly', 'priority' => '0.4'],
        ['path' => '/booking', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['path' => '/articles', 'changefreq' => 'weekly', 'priority' => '0.6'],
        ['path' => '/delivery/anapa', 'changefreq' => 'yearly', 'priority' => '0.4'],
        ['path' => '/delivery/gelendzhik', 'changefreq' => 'yearly', 'priority' => '0.4'],
    ],

    /**
     * Paths listed in tenant llms.txt (relative to canonical base).
     */
    'llms_paths' => [
        '/',
        '/motorcycles',
        '/booking',
        '/contacts',
        '/faq',
        '/about',
        '/prices',
        '/reviews',
        '/usloviya-arenda',
    ],
];
