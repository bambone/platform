<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateAllTenantStorageUsageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public bool $runChildrenSynchronously = false,
    ) {}

    public function handle(): void
    {
        Tenant::query()->orderBy('id')->chunkById(50, function ($tenants): void {
            foreach ($tenants as $tenant) {
                $id = (int) $tenant->id;
                if ($this->runChildrenSynchronously) {
                    RecalculateTenantStorageUsageJob::dispatchSync($id);
                } else {
                    RecalculateTenantStorageUsageJob::dispatch($id);
                }
            }
        });
    }
}
