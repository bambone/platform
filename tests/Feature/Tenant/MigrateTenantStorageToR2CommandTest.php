<?php

namespace Tests\Feature\Tenant;

use Tests\TestCase;

class MigrateTenantStorageToR2CommandTest extends TestCase
{
    public function test_exits_failure_when_disk_unknown(): void
    {
        $this->artisan('tenant-storage:migrate-to-r2', [
            '--from-public' => 'disk-that-does-not-exist-xyz',
            '--to-public' => 'public',
            '--from-private' => 'local',
            '--to-private' => 'local',
        ])->assertExitCode(1);
    }

    public function test_exits_success_when_source_and_target_disks_identical(): void
    {
        $this->artisan('tenant-storage:migrate-to-r2', [
            '--dry-run' => true,
            '--from-public' => 'public',
            '--to-public' => 'public',
            '--from-private' => 'local',
            '--to-private' => 'local',
        ])->assertExitCode(0);
    }
}
