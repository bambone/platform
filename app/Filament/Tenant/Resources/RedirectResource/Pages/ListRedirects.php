<?php

namespace App\Filament\Tenant\Resources\RedirectResource\Pages;

use App\Filament\Tenant\Resources\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
