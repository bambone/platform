<?php

namespace App\Filament\Tenant\Resources\AvailabilityExceptionResource\Pages;

use App\Filament\Tenant\Resources\AvailabilityExceptionResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvailabilityExceptions extends ListRecords
{
    protected static string $resource = AvailabilityExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'availabilityExceptionsWhatIs',
                [
                    'Исключения из базовых правил: выходные, закрытие на даты, особые интервалы.',
                    '',
                    'Имеют приоритет над обычными правилами в расчёте слотов.',
                ],
                'Справка по исключениям доступности',
            ),
            CreateAction::make(),
        ];
    }
}
