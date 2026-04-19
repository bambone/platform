<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\BookingSettingsPresetResource\Pages;

use App\Filament\Tenant\Resources\BookingSettingsPresetResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBookingSettingsPresets extends ListRecords
{
    protected static string $resource = BookingSettingsPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'bookingSettingsPresetsWhatIs',
                [
                    'Группа — шаблон параметров записи (слоты, горизонт, подтверждение и т.д.).',
                    'Подключают к услугам и применяют с карточек каталога.',
                    '',
                    '«Быстрый старт (мастер запуска)» часто появляется из анкеты запуска — это нормальный стартовый пресет.',
                ],
                'Что такое группа настроек записи',
            ),
            CreateAction::make(),
        ];
    }
}
