<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;

use App\Filament\Tenant\Resources\TenantServiceProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantServicePrograms extends ListRecords
{
    protected static string $resource = TenantServiceProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
