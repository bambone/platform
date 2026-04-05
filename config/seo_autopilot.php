<?php

/**
 * Tenant SEO Autopilot v1 — deterministic defaults (no Route::getRoutes scan).
 *
 * @see \App\Services\Seo\TenantSeoRouteOverridesBuilder
 * @see \App\Services\Seo\TenantSeoLintService
 */
return [
    /*
     * Route names eligible for default `seo.route_overrides` (must exist in config/seo_routes.php).
     * Excludes entity-specific routes (page.show, motorcycle.show, booking.show).
     */
    'route_overrides_allowlist' => [
        'home',
        'contacts',
        'terms',
        'motorcycles.index',
        'prices',
        'order',
        'reviews',
        'faq',
        'about',
        'booking.index',
        'booking.checkout',
        'booking.thank-you',
        'articles.index',
    ],

    /*
     * Lint checks these named routes (internal mode uses canonical base URL + route path).
     */
    'lint_route_names' => [
        'home',
        'motorcycles.index',
        'booking.index',
        'contacts',
        'faq',
        'terms',
        'about',
    ],

    'llms_max_entries' => 10,

    /** Max FAQ Question/Answer pairs embedded in FAQPage JSON-LD. */
    'faq_json_ld_max_items' => 20,

    'lint_og_image_timeout_seconds' => 5,

    'lint_include_sample_motorcycle' => true,
];
