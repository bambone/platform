<?php

namespace App\Filament\Tenant\Resources\CrmRequestResource\Pages;

use App\Filament\Tenant\Resources\CrmRequestResource;
use App\Filament\Tenant\Resources\CrmRequestResource\Widgets\CrmRequestStatsWidget;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmRequests extends ListRecords
{
    protected static string $resource = CrmRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'crmRequestsWhatIs',
                [
                    'Заявки CRM: обращения с форм сайта и операторского ввода.',
                    '',
                    'Статусы, ответственный и экспорт — по вашим правам.',
                ],
                'Справка по заявкам CRM',
            ),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CrmRequestStatsWidget::class,
        ];
    }
}
