<?php

namespace App\Filament\Tenant\Resources\LocationLandingPageResource\Pages;

use App\Filament\Tenant\Resources\LocationLandingPageResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocationLandingPages extends ListRecords
{
    protected static string $resource = LocationLandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'locationLandingPagesWhatIs',
                [
                    'Посадочные под локации (город, точка): контент и SEO для геозапросов.',
                    '',
                    'Связывайте с записями в «Локациях».',
                ],
                'Справка по локальным посадочным',
            ),
            CreateAction::make(),
        ];
    }
}
