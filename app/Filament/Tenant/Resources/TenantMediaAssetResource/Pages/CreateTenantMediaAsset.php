<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantMediaAssetResource\Pages;

use App\Filament\Tenant\Resources\TenantMediaAssetResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTenantMediaAsset extends CreateRecord
{
    protected static string $resource = TenantMediaAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = currentTenant()?->id;

        return $data;
    }
}

