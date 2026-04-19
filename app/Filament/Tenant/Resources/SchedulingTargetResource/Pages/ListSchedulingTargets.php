<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\SchedulingTargetResource\Pages;

use App\Filament\Tenant\Resources\SchedulingTargetResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchedulingTargets extends ListRecords
{
    protected static string $resource = SchedulingTargetResource::class;

    protected function getHeaderActions(): array
    {
        $hint = TenantPanelHintHeaderAction::makeLines(
            'schedulingTargetsWhatIs',
            [
                'Цель — связка сущности (тип + ID) и ресурсов расписания для слотов.',
                'Чаще создаётся вместе с услугей с записью.',
                '',
                'Кнопка «Создать» есть только если уже есть «Ресурсы расписания».',
            ],
            'Что такое цель расписания',
        );

        if (! SchedulingTargetResource::canStartCreatingTarget()) {
            return [$hint];
        }

        return [$hint, CreateAction::make()];
    }
}
