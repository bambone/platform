<?php

return [
    /**
     * Tenant admin: readiness widget, setup center, guided overlay, setup sessions.
     * When false: hide Filament UI; overlay hooks do not render (state rows may remain).
     */
    'tenant_site_setup_framework' => (bool) env('FEATURE_TENANT_SITE_SETUP', true),

    /**
     * Плавающая JSON-панель отладки гида (payload.guided_dev_debug + клиентские причины в tenant-admin-site-setup.js).
     * По умолчанию выключена: при APP_DEBUG=true панель иначе мешала бы везде в кабинете.
     * Включать только при отладке гида: вместе с APP_DEBUG задать env=true.
     */
    'tenant_site_setup_guided_debug' => (bool) env('FEATURE_TENANT_SITE_SETUP_GUIDED_DEBUG', false),
];
