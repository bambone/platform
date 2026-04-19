<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantLocationResource\Pages;

use App\Filament\Tenant\Resources\TenantLocationResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantLocations extends ListRecords
{
    protected static string $resource = TenantLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'tenantLocationsWhatIs',
                [
                    'Локации бизнеса: адрес, часовой пояс, привязка к страницам и записям.',
                    '',
                    'Используются на сайте и в операторских формах, где выбрана локация.',
                ],
                'Справка по локациям',
            ),
            CreateAction::make(),
        ];
    }
}
