<?php

namespace App\Filament\Platform\Resources\TenantDomainResource\Pages;

use App\Filament\Platform\Resources\TenantDomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantDomains extends ListRecords
{
    protected static string $resource = TenantDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
