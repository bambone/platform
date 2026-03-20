<?php

namespace App\Filament\Tenant\Resources\RentalUnitResource\Pages;

use App\Filament\Tenant\Resources\RentalUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalUnits extends ListRecords
{
    protected static string $resource = RentalUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
