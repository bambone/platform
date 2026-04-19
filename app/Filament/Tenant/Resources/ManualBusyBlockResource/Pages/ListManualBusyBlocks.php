<?php

namespace App\Filament\Tenant\Resources\ManualBusyBlockResource\Pages;

use App\Filament\Tenant\Resources\ManualBusyBlockResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListManualBusyBlocks extends ListRecords
{
    protected static string $resource = ManualBusyBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'manualBusyBlocksWhatIs',
                [
                    'Ручные блоки занятости закрывают время в расписании без события из внешнего календаря.',
                    '',
                    'Удобно для отпуска или разового «занято».',
                ],
                'Справка по ручной занятости',
            ),
            CreateAction::make(),
        ];
    }
}
