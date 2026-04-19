<?php

namespace App\Filament\Tenant\Resources\AvailabilityRuleResource\Pages;

use App\Filament\Tenant\Resources\AvailabilityRuleResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvailabilityRules extends ListRecords
{
    protected static string $resource = AvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'availabilityRulesWhatIs',
                [
                    'Базовые правила доступности: в какие дни и интервалы можно предлагать слоты.',
                    '',
                    'Работают вместе с ресурсами расписания; точечные изменения — в «Исключениях».',
                ],
                'Справка по правилам доступности',
            ),
            CreateAction::make(),
        ];
    }
}
