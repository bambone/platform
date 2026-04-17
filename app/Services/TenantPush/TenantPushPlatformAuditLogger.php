<?php

declare(strict_types=1);

namespace App\Services\TenantPush;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Аудит изменений platform-owned полей {@see \App\Models\TenantPushSettings} из панели платформы.
 */
final class TenantPushPlatformAuditLogger
{
    /**
     * @param  array<string, mixed>  $beforeScalars  Ключи: push_override, commercial_service_active, self_serve_allowed
     * @param  array<string, mixed>  $afterScalars
     */
    public function logIfChanged(
        int $tenantId,
        ?Authenticatable $actor,
        array $beforeScalars,
        array $afterScalars,
    ): void {
        $keys = ['push_override', 'commercial_service_active', 'self_serve_allowed'];
        $changed = false;
        foreach ($keys as $k) {
            $b = $beforeScalars[$k] ?? null;
            $a = $afterScalars[$k] ?? null;
            if ($b !== $a) {
                $changed = true;
                break;
            }
        }
        if (! $changed) {
            return;
        }

        $actorId = $actor instanceof User ? $actor->id : ($actor?->getAuthIdentifier());
        $actorLabel = $actor instanceof User ? $actor->email : (string) $actorId;

        Log::info('tenant_push_platform_settings_updated', [
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'actor' => $actorLabel,
            'before' => [
                'push_override' => $beforeScalars['push_override'] ?? null,
                'commercial_service_active' => $beforeScalars['commercial_service_active'] ?? null,
                'self_serve_allowed' => $beforeScalars['self_serve_allowed'] ?? null,
            ],
            'after' => [
                'push_override' => $afterScalars['push_override'] ?? null,
                'commercial_service_active' => $afterScalars['commercial_service_active'] ?? null,
                'self_serve_allowed' => $afterScalars['self_serve_allowed'] ?? null,
            ],
        ]);
    }
}
