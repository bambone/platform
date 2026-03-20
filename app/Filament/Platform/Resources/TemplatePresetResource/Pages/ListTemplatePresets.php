<?php

namespace App\Filament\Platform\Resources\TemplatePresetResource\Pages;

use App\Filament\Platform\Resources\TemplatePresetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTemplatePresets extends ListRecords
{
    protected static string $resource = TemplatePresetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
