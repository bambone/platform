<?php

namespace App\Filament\Platform\Resources\DomainTermResource\Pages;

use App\Filament\Platform\Resources\DomainTermResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDomainTerms extends ListRecords
{
    protected static string $resource = DomainTermResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
