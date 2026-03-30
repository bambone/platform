<?php

namespace App\Filament\Tenant\Concerns;

use App\Terminology\TenantTerminologyService;

trait ResolvesDomainTermLabels
{
    protected static function domainTermLabel(string $termKey, string $fallback): string
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return $fallback;
        }

        return app(TenantTerminologyService::class)->label($tenant, $termKey);
    }
}
