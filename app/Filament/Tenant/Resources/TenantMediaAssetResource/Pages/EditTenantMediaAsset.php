<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantMediaAssetResource\Pages;

use App\Filament\Tenant\Resources\TenantMediaAssetResource;
use Filament\Resources\Pages\EditRecord;

final class EditTenantMediaAsset extends EditRecord
{
    protected static string $resource = TenantMediaAssetResource::class;
}

