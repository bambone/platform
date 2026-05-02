<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantPublicSiteThemeResource\Pages;

use App\Filament\Platform\Resources\TenantPublicSiteThemeResource;
use Filament\Resources\Pages\EditRecord;

class EditTenantPublicSiteTheme extends EditRecord
{
    protected static string $resource = TenantPublicSiteThemeResource::class;
}
