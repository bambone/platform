<?php

namespace App\Filament\Tenant\Resources\RentalUnitResource\Pages;

use App\Filament\Tenant\Resources\RentalUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRentalUnit extends EditRecord
{
    protected static string $resource = RentalUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
