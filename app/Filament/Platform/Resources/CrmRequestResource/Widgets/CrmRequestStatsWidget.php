<?php

namespace App\Filament\Platform\Resources\CrmRequestResource\Widgets;

use App\Filament\Platform\Resources\CrmRequestResource;
use App\Filament\Shared\CRM\CrmRequestStatsHelper;
use Filament\Widgets\StatsOverviewWidget;

class CrmRequestStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $panel = 'platform';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return CrmRequestStatsHelper::stats(CrmRequestResource::getEloquentQuery());
    }
}
