<?php

namespace App\Filament\Platform\Pages;

use Filament\Pages\Dashboard;

/**
 * Отдельный путь от корня панели: иначе GET /platform/ занят RedirectToHomeController,
 * и маршрут filament.{panel}.pages.dashboard не регистрируется (см. laravel.log).
 */
class PlatformDashboard extends Dashboard
{
    protected static string $routePath = '/dashboard';
}
