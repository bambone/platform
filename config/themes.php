<?php

return [

    /**
     * Ключ темы по умолчанию, если у тенанта пустой/невалидный theme_key.
     * Должен совпадать с каталогом resources/themes/{key}/theme.json.
     */
    'default_key' => env('PLATFORM_DEFAULT_THEME_KEY', 'moto'),

    /**
     * Префикс URL для ассетов темы на сайте (public/themes/moto/...).
     * Источники: resources/themes/{key}/public (маршрут /theme/build/… или sync) и theme:publish-bundled.
     */
    'public_asset_root' => 'themes',

    /**
     * Переходный fallback, если файла ещё нет в public/themes/{key}/...
     * (раньше — public/images/motolevins/...).
     */
    'legacy_asset_url_prefix' => env('THEME_LEGACY_ASSET_PREFIX', 'images/motolevins'),

];
