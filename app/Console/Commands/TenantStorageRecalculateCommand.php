<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateAllTenantStorageUsageJob;
use App\Jobs\RecalculateTenantStorageUsageJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantStorageRecalculateCommand extends Command
{
    protected $signature = 'tenant-storage:recalculate {tenant? : Tenant ID or omit for all}';

    protected $description = 'Recalculate tenant object storage usage from disk (public + private prefixes).';

    public function handle(): int
    {
        $arg = $this->argument('tenant');
        if ($arg === null || $arg === '') {
            RecalculateAllTenantStorageUsageJob::dispatchSync(true);
            $this->info('Recalculated storage usage for all tenants.');

            return self::SUCCESS;
        }

        $id = (int) $arg;
        if ($id <= 0 || Tenant::query()->whereKey($id)->doesntExist()) {
            $this->error('Invalid or unknown tenant ID.');

            return self::FAILURE;
        }

        RecalculateTenantStorageUsageJob::dispatchSync($id);
        $this->info("Recalculated storage usage for tenant #{$id}.");

        return self::SUCCESS;
    }
}
