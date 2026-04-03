<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use App\Tenant\StorageQuota\TenantStorageUsageScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateTenantStorageUsageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $tenantId,
    ) {}

    public function handle(TenantStorageUsageScanner $scanner, TenantStorageQuotaService $quotas): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        try {
            $result = $scanner->scan($this->tenantId);
            $quotas->markSyncSuccess($tenant, $result->totalBytes, $result->toSummaryJson());
        } catch (Throwable $e) {
            Log::error('tenant_storage_scan_failed', [
                'tenant_id' => $this->tenantId,
                'message' => $e->getMessage(),
            ]);
            $quotas->markSyncFailure($tenant, $e->getMessage());
        }
    }
}
