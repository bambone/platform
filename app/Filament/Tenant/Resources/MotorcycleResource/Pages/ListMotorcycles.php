<?php

namespace App\Filament\Tenant\Resources\MotorcycleResource\Pages;

use App\Filament\Tenant\Resources\MotorcycleResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycles extends ListRecords
{
    protected static string $resource = MotorcycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'motorcyclesWhatIs',
                [
                    'Объекты парка (техника): карточки для каталога и сценариев бронирования.',
                    '',
                    'Настройки темы и модуля могут скрывать часть полей.',
                ],
                'Справка по технике парка',
            ),
            Actions\CreateAction::make(),
        ];
    }
}
