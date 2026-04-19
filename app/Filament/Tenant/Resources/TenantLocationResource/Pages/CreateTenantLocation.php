<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantLocationResource\Pages;

use App\Filament\Tenant\Resources\TenantLocationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateTenantLocation extends CreateRecord
{
    protected static string $resource = TenantLocationResource::class;

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Создать и добавить ещё');
    }
}
