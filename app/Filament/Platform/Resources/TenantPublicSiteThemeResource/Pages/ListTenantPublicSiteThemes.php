<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantPublicSiteThemeResource\Pages;

use App\Filament\Platform\Resources\TenantPublicSiteThemeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantPublicSiteThemes extends ListRecords
{
    protected static string $resource = TenantPublicSiteThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
