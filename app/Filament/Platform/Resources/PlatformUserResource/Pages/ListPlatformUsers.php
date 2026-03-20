<?php

namespace App\Filament\Platform\Resources\PlatformUserResource\Pages;

use App\Filament\Platform\Resources\PlatformUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformUsers extends ListRecords
{
    protected static string $resource = PlatformUserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
