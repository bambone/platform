<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantPublicSiteThemeResource\Pages;

use App\Filament\Platform\Resources\TenantPublicSiteThemeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantPublicSiteTheme extends CreateRecord
{
    protected static string $resource = TenantPublicSiteThemeResource::class;
}
