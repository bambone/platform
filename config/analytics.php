<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Render analytics snippets on the public tenant site
    |--------------------------------------------------------------------------
    |
    | When false, AnalyticsSnippetRenderer returns an empty string for the
    | corresponding environment. force_render overrides these (for debugging).
    |
    */

    'render_in_local' => false,

    'render_in_testing' => false,

    'render_in_staging' => false,

    'force_render' => env('ANALYTICS_FORCE_RENDER', false),

    /*
    |--------------------------------------------------------------------------
    | Providers (feature flags + future CSP documentation)
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'yandex_metrica' => [
            'enabled' => true,
            'allowed_script_hosts' => [
                'mc.yandex.ru',
            ],
        ],

        'ga4' => [
            'enabled' => true,
            'allowed_script_hosts' => [
                'www.googletagmanager.com',
                'www.google-analytics.com',
            ],
        ],

        'gtm' => [
            'enabled' => false,
            'allowed_script_hosts' => [
                'www.googletagmanager.com',
            ],
        ],

    ],

];
