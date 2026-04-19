<?php

namespace App\Filament\Tenant\Resources\NotificationDeliveryResource\Pages;

use App\Filament\Tenant\Resources\NotificationDeliveryResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationDeliveries extends ListRecords
{
    protected static string $resource = NotificationDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'notificationDeliveriesWhatIs',
                [
                    'Журнал отправок уведомлений: статусы и ошибки доставки.',
                    '',
                    'Только просмотр; новые отправки создаются событиями и правилами.',
                ],
                'Справка по истории доставок',
            ),
        ];
    }
}
