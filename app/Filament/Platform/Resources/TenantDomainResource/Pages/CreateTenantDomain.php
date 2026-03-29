<?php

namespace App\Filament\Platform\Resources\TenantDomainResource\Pages;

use App\Filament\Platform\Resources\TenantDomainResource;
use App\Models\TenantDomain;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantDomain extends CreateRecord
{
    protected static string $resource = TenantDomainResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? '') === TenantDomain::TYPE_SUBDOMAIN) {
            $data['status'] = TenantDomain::STATUS_ACTIVE;
            $data['ssl_status'] = TenantDomain::SSL_NOT_REQUIRED;
            $data['dns_target'] = '';
        } else {
            $data['status'] = $data['status'] ?? TenantDomain::STATUS_PENDING;
            $data['ssl_status'] = $data['ssl_status'] ?? TenantDomain::SSL_PENDING;
        }

        return $data;
    }
}
