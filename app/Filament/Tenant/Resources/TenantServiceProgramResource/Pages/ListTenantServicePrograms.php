<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;

use App\Filament\Tenant\Resources\TenantServiceProgramResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantServicePrograms extends ListRecords
{
    protected static string $resource = TenantServiceProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'tenantServiceProgramsWhatIs',
                [
                    'Программы (пакеты услуг / обучения) для публичного каталога и сценариев записи.',
                    '',
                    'CTA и страница записи настраиваются в параметрах сайта.',
                ],
                'Справка по программам',
            ),
            CreateAction::make()
                ->extraAttributes(['data-setup-action' => 'programs.create_action']),
        ];
    }
}
