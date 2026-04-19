<?php

namespace App\Filament\Tenant\Resources\CalendarConnectionResource\Pages;

use App\Filament\Tenant\Resources\CalendarConnectionResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalendarConnections extends ListRecords
{
    protected static string $resource = CalendarConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'calendarConnectionsWhatIs',
                [
                    'Подключения к внешним календарям.',
                    '',
                    'После создания включите подписку на нужные календари.',
                    'При необходимости добавьте сопоставления занятости, чтобы внешние события закрывали слоты.',
                ],
                'Справка по календарям',
            ),
            CreateAction::make(),
        ];
    }
}
