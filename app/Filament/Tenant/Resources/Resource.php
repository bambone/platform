<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Support\TenantFilamentTablePagination;
use Filament\Resources\Resource as BaseResource;
use Filament\Tables\Table;

abstract class Resource extends BaseResource
{
    public static function configureTable(Table $table): void
    {
        parent::configureTable($table);

        TenantFilamentTablePagination::applyForAdminPanel($table);
    }
}
