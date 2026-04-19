<?php

namespace App\Filament\Tenant\Resources\BookingResource\Pages;

use App\Filament\Tenant\Forms\ManualOperatorBookingForm;
use App\Filament\Tenant\Resources\BookingResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'bookingsListWhatIs',
                [
                    'Журнал бронирований и записей.',
                    '',
                    'Кнопка создания открывает операторскую форму новой записи (права на создание обязательны).',
                ],
                'Справка по списку записей',
            ),
            ManualOperatorBookingForm::standaloneBookingCreateAction(
                afterSubmit: function (): void {
                    $this->dispatch('$refresh');
                },
            ),
        ];
    }
}
