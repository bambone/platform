<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Pages\Dashboard;

class TenantDashboard extends Dashboard
{
    protected static ?string $title = 'Обзор';

    protected static ?string $navigationLabel = 'Главная';

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'tenantDashboardWhatIs',
                [
                    'Главная кабинета клиента.',
                    '',
                    'Виджеты и быстрые действия зависят от ваших прав и включённых модулей.',
                ],
                'Справка по главной',
            ),
        ];
    }
}
