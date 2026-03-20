<?php

namespace App\Filament\Tenant\Resources\MotorcycleResource\Pages;

use App\Filament\Tenant\Resources\MotorcycleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycles extends ListRecords
{
    protected static string $resource = MotorcycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
