<?php

namespace App\Filament\Tenant\Resources\IntegrationResource\Pages;

use App\Filament\Tenant\Resources\IntegrationResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrations extends ListRecords
{
    protected static string $resource = IntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'integrationsWhatIs',
                [
                    'Внешние интеграции (webhook, ключи API и т.п. по возможностям продукта).',
                    '',
                    'Храните секреты аккуратно; удаление отключает обмен.',
                ],
                'Справка по интеграциям',
            ),
            CreateAction::make(),
        ];
    }
}
