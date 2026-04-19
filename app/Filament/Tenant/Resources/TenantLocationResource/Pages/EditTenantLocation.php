<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantLocationResource\Pages;

use App\Filament\Tenant\Resources\TenantLocationResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditTenantLocation extends EditRecord
{
    protected static string $resource = TenantLocationResource::class;

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;
}
