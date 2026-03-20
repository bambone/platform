<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\FilamentAuthBootstrapServiceProvider;

return [
    AppServiceProvider::class,
    FilamentAuthBootstrapServiceProvider::class,
    AdminPanelProvider::class,
    PlatformPanelProvider::class,
];
