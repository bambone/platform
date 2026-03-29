<?php

namespace App\Filament\Platform\Resources\TenantDomainResource\Pages;

use App\Filament\Platform\Resources\TenantDomainResource;
use App\Models\TenantDomain;
use Filament\Resources\Pages\EditRecord;

class EditTenantDomain extends EditRecord
{
    protected static string $resource = TenantDomainResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? '') === TenantDomain::TYPE_SUBDOMAIN) {
            $data['ssl_status'] = TenantDomain::SSL_NOT_REQUIRED;
            $data['dns_target'] = '';
        }

        return $data;
    }
}
