<?php

namespace App\Filament\Tenant\Resources\RentalUnitResource\Pages;

use App\Filament\Tenant\Resources\RentalUnitResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalUnits extends ListRecords
{
    protected static string $resource = RentalUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'rentalUnitsWhatIs',
                [
                    'Единицы парка / точки выдачи для сценариев с техникой и фильтрами календаря.',
                    '',
                    'Связь с техникой настраивается в карточках.',
                ],
                'Справка по единицам парка',
            ),
            CreateAction::make(),
        ];
    }
}
