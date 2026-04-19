<?php

namespace App\Filament\Tenant\Resources\NotificationDestinationResource\Pages;

use App\Filament\Tenant\Resources\NotificationDestinationResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationDestinations extends ListRecords
{
    protected static string $resource = NotificationDestinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'notificationDestinationsWhatIs',
                [
                    'Получатели уведомлений: email, Telegram, браузер и др.',
                    '',
                    'Правила из списка «Подписки» ссылаются на эти записи.',
                ],
                'Справка по получателям',
            ),
            CreateAction::make(),
        ];
    }
}
