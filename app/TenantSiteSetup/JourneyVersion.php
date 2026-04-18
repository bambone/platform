<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;

/**
 * Stable hash when registry, profile shape, theme/features change — resume must not follow stale routes.
 */
final class JourneyVersion
{
    public const REGISTRY_VERSION = 1;

    public const CATEGORY_REGISTRY_VERSION = 1;

    public function compute(Tenant $tenant, SetupProfileRepository $profiles): string
    {
        $profile = $profiles->get((int) $tenant->id);
        ksort($profile);
        $payload = [
            'rv' => self::REGISTRY_VERSION,
            'cv' => self::CATEGORY_REGISTRY_VERSION,
            'pv' => $profiles->schemaVersion(),
            'profile' => json_encode($profile),
            'desired_branch' => trim((string) ($profile['desired_branch'] ?? '')),
            'theme' => (string) $tenant->theme_key,
            'sched' => (bool) $tenant->scheduling_module_enabled,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
