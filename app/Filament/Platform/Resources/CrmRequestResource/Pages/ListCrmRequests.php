<?php

namespace App\Filament\Platform\Resources\CrmRequestResource\Pages;

use App\Filament\Platform\Resources\CrmRequestResource;
use App\Filament\Platform\Resources\CrmRequestResource\Widgets\CrmRequestStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListCrmRequests extends ListRecords
{
    protected static string $resource = CrmRequestResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CrmRequestStatsWidget::class,
        ];
    }
}
