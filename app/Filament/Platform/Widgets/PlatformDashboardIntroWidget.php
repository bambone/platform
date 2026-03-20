<?php

namespace App\Filament\Platform\Widgets;

use Filament\Widgets\Widget;

class PlatformDashboardIntroWidget extends Widget
{
    protected static ?int $sort = 0;

    protected static ?string $panel = 'platform';

    protected string $view = 'filament.platform.widgets.dashboard-intro';

    protected int|string|array $columnSpan = 'full';
}
