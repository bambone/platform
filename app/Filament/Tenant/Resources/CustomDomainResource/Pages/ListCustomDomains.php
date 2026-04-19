<?php

namespace App\Filament\Tenant\Resources\CustomDomainResource\Pages;

use App\Filament\Tenant\Resources\CustomDomainResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomDomains extends ListRecords
{
    protected static string $resource = CustomDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'customDomainsWhatIs',
                [
                    'Свои домены для публичного сайта: привязка, статус проверки и SSL.',
                    '',
                    'Технические записи DNS настраиваются у регистратора.',
                ],
                'Справка по доменам',
            ),
            CreateAction::make(),
        ];
    }
}
