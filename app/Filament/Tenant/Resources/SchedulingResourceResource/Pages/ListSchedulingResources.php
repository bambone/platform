<?php

namespace App\Filament\Tenant\Resources\SchedulingResourceResource\Pages;

use App\Filament\Tenant\Resources\SchedulingResourceResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchedulingResources extends ListRecords
{
    protected static string $resource = SchedulingResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'schedulingResourcesWhatIs',
                [
                    'Ресурсы расписания (кабинет, сотрудник, дорожка и т.п.), к которым строятся слоты.',
                    '',
                    'Цели расписания ссылаются на эти ресурсы; без них онлайн-запись не настроить.',
                ],
                'Справка по ресурсам расписания',
            ),
            CreateAction::make(),
        ];
    }
}
