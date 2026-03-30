<?php

namespace App\Filament\Platform\Resources\DomainLocalizationPresetResource\Pages;

use App\Filament\Platform\Resources\DomainLocalizationPresetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDomainLocalizationPresets extends ListRecords
{
    protected static string $resource = DomainLocalizationPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
