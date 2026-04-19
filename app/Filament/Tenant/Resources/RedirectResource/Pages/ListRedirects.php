<?php

namespace App\Filament\Tenant\Resources\RedirectResource\Pages;

use App\Filament\Tenant\Resources\RedirectResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'redirectsWhatIs',
                [
                    'Перенаправления со старых путей на новые на вашем домене.',
                    '',
                    'Полезно после смены slug или объединения страниц.',
                ],
                'Справка по редиректам',
            ),
            CreateAction::make(),
        ];
    }
}
