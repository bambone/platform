<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantMediaAssetResource\Pages;

use App\Filament\Tenant\Resources\TenantMediaAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTenantMediaAssets extends ListRecords
{
    protected static string $resource = TenantMediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

