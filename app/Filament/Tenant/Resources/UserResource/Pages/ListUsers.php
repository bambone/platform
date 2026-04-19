<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Filament\Tenant\Resources\UserResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'tenantUsersWhatIs',
                [
                    'Пользователи с доступом в этот кабинет клиента.',
                    '',
                    'Роль и статус задаются в связке «клиент — сотрудник»; один человек может быть в нескольких клиентах.',
                ],
                'Справка по команде кабинета',
            ),
            CreateAction::make(),
        ];
    }
}
