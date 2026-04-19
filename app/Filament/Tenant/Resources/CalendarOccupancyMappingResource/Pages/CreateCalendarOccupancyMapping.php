<?php

namespace App\Filament\Tenant\Resources\CalendarOccupancyMappingResource\Pages;

use App\Filament\Tenant\Resources\CalendarOccupancyMappingResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCalendarOccupancyMapping extends CreateRecord
{
    protected static string $resource = CalendarOccupancyMappingResource::class;

    public function mount(): void
    {
        if (! CalendarOccupancyMappingResource::canStartCreatingMapping()) {
            Notification::make()
                ->title('Сначала настройте календарь')
                ->body(
                    'Нужны включённые календарные интеграции и хотя бы одна подписка на календарь в карточке подключения («Календари (подключения)»). Иначе обязательное поле «Календарь (подписка)» не заполнить.'
                )
                ->warning()
                ->send();
            $this->redirect(CalendarOccupancyMappingResource::getUrl('index'));

            return;
        }

        parent::mount();
    }
}
