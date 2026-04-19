<?php

namespace App\Filament\Tenant\Resources\BookableServiceResource\Pages;

use App\Filament\Tenant\Resources\BookableServiceResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBookableServices extends ListRecords
{
    protected static string $resource = BookableServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'bookableServicesWhatIs',
                [
                    'Услуги с онлайн-записью: длительность, пресет параметров слотов, привязка к целям расписания.',
                    '',
                    'В каталоге на сайте видны, если включена публикация.',
                ],
                'Справка по услугам с записью',
            ),
            CreateAction::make(),
        ];
    }
}
