<?php

namespace App\Filament\Tenant\Resources\CrmRequestResource\Widgets;

use App\Filament\Shared\CRM\CrmRequestStatsHelper;
use App\Filament\Tenant\Resources\CrmRequestResource;
use Filament\Widgets\StatsOverviewWidget;

class CrmRequestStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return CrmRequestStatsHelper::stats(CrmRequestResource::getEloquentQuery());
    }
}
