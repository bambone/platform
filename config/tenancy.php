<?php

return [
    'central_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', ''))))),

    'root_domain' => env('TENANCY_ROOT_DOMAIN', 'rentbase.su'),

    'server_ip' => env('TENANCY_SERVER_IP', '91.219.63.38'),

    'cname_target' => env('TENANCY_CNAME_TARGET', 'domains.'.env('TENANCY_ROOT_DOMAIN', 'rentbase.su')),

    'cache_ttl' => (int) env('TENANCY_RESOLVER_CACHE_TTL', 300),

    /*
    | Резерв: раньше кэшировали payload главной (TTL > 0). Отключено в коде — Eloquent в Cache ломает unserialize.
    | Ключ оставлен для совместимости .env; сброс старых ключей — HomeController::forgetCachedPayloadForTenant.
    */
    'public_home_cache_ttl' => (int) env('TENANCY_PUBLIC_HOME_CACHE_TTL', 0),

    'custom_domains' => [
        'verification_prefix' => env('TENANCY_VERIFICATION_PREFIX', '_rentbase-verification'),
        'webroot' => env('TENANCY_LE_WEBROOT', '/var/www/letsencrypt'),
        'provision_script' => env('TENANCY_PROVISION_SCRIPT', '/usr/local/bin/rentbase-provision-domain'),
    ],

    'provision_use_sudo' => (bool) env('TENANCY_PROVISION_USE_SUDO', false),

    /*
    | When true, TenantViewResolver logs debug lines (tenant id, theme keys, logical name, resolved view).
    | Prefer explicit flag over APP_DEBUG in production tracing.
    */
    'log_view_resolution' => (bool) env('TENANCY_LOG_VIEW_RESOLUTION', false),
];
