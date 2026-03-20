<?php

use App\Models\Tenant;
use App\Services\CurrentTenantManager;

if (! function_exists('currentTenant')) {
    function currentTenant(): ?Tenant
    {
        return app(CurrentTenantManager::class)->getTenant();
    }
}
