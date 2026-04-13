<?php

namespace App\Filament\Platform\Widgets;

use Filament\Widgets\Widget;

class PlatformDashboardIntroWidget extends Widget
{
    /** Отключено: после логина ленивый догруз мог оставлять fi-loading-section поверх UI (см. platform overlay diagnostics). */
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected static ?string $panel = 'platform';

    protected string $view = 'filament.platform.widgets.dashboard-intro';

    protected int|string|array $columnSpan = 'full';
}
