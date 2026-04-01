<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Support\Analytics\AnalyticsSettingsData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for tenant analytics settings audit. Swap implementation later
 * (e.g. spatie/laravel-activitylog) without touching Filament pages.
 */
final class TenantAnalyticsAuditLogger
{
    public function logUpdated(
        int $tenantId,
        ?Authenticatable $actor,
        AnalyticsSettingsData $before,
        AnalyticsSettingsData $after,
    ): void {
        if ($before->equals($after)) {
            return;
        }

        $actorId = $actor instanceof User ? $actor->id : ($actor?->getAuthIdentifier());
        $actorLabel = $actor instanceof User ? $actor->email : (string) $actorId;

        Log::info('tenant_analytics_settings_updated', [
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'actor' => $actorLabel,
            'before' => $before->sanitizedForLog(),
            'after' => $after->sanitizedForLog(),
        ]);
    }
}
