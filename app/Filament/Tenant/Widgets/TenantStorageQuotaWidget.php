<?php

namespace App\Filament\Tenant\Widgets;

use App\Tenant\StorageQuota\TenantStorageQuotaData;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use App\Tenant\StorageQuota\TenantStorageQuotaStatus;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

class TenantStorageQuotaWidget extends Widget
{
    protected string $view = 'filament.tenant.widgets.tenant-storage-quota-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    #[Computed]
    public function quota(): ?TenantStorageQuotaData
    {
        $t = currentTenant();
        if ($t === null) {
            return null;
        }

        return app(TenantStorageQuotaService::class)->forTenant($t);
    }

    public function isCriticalLayout(): bool
    {
        $q = $this->quota;

        return $q !== null && (
            $q->status === TenantStorageQuotaStatus::Critical10
            || $q->status === TenantStorageQuotaStatus::Exceeded
        );
    }
}
