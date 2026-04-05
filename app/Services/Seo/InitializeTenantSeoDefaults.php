<?php

namespace App\Services\Seo;

use App\Models\Tenant;
use App\Services\Seo\Data\TenantSeoAutopilotResult;

/**
 * Use-case entry for tenant provisioning and CLI: applies SEO autopilot defaults.
 */
final class InitializeTenantSeoDefaults
{
    public function __construct(
        private TenantSeoAutopilotService $autopilot,
    ) {}

    public function execute(Tenant $tenant, bool $force = false, bool $dryRun = false): TenantSeoAutopilotResult
    {
        return $this->autopilot->run($tenant, $force, $dryRun);
    }
}
