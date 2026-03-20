<?php

namespace App\Filament\Tenant\Widgets;

use Filament\Widgets\Widget;

class TenantDashboardIntroWidget extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.tenant.widgets.dashboard-intro';

    protected int|string|array $columnSpan = 'full';
}
