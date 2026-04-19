<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\CalendarOccupancyMappingResource\Pages;

use App\Filament\Tenant\Resources\CalendarOccupancyMappingResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalendarOccupancyMappings extends ListRecords
{
    protected static string $resource = CalendarOccupancyMappingResource::class;

    protected function getHeaderActions(): array
    {
        $hint = TenantPanelHintHeaderAction::makeLines(
            'calendarOccupancyMappingWhatIs',
            [
                'Сопоставление связывает события из подключённого календаря с целью или ресурсом — так внешняя занятость попадает в расчёт слотов.',
                '',
                'Нужны включённые интеграции и подписка на календарь в карточке подключения.',
                'Кнопка «Создать» видна только когда подписка уже есть.',
            ],
            'Что такое сопоставление занятости',
        );

        if (! CalendarOccupancyMappingResource::canStartCreatingMapping()) {
            return [$hint];
        }

        return [$hint, CreateAction::make()];
    }
}
