<?php

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;

/**
 * Resolves tenants for SEO artisan commands (onboarding often leaves status {@see Tenant::STATUS_TRIAL}).
 *
 * @see ResolvesTenantArgument
 */
trait ResolvesTenantForSeoCommands
{
    protected function resolveTenantForSeo(string $key): Tenant
    {
        if (ctype_digit($key)) {
            return Tenant::query()
                ->whereIn('status', ['trial', 'active'])
                ->whereKey((int) $key)
                ->firstOrFail();
        }

        return Tenant::query()
            ->whereIn('status', ['trial', 'active'])
            ->where('slug', $key)
            ->firstOrFail();
    }
}
