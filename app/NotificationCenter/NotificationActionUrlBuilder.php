<?php

namespace App\NotificationCenter;

use App\Models\Booking;
use App\Models\CrmRequest;
use App\Models\Lead;
use App\Models\Tenant;

/**
 * Tenant admin deep links only (never platform host). Uses cabinet base URL + path.
 * Returns null when the tenant has no cabinet base URL, the subject type is unsupported,
 * or the subject row does not belong to the tenant (avoids broken deep links after delete/mismatch).
 *
 * Tech debt: routing keys are `class_basename()` of models and hand-built paths; if Filament slugs
 * diverge or two models share a basename, prefer an explicit registry + named routes.
 */
final class NotificationActionUrlBuilder
{
    public function urlForSubject(Tenant $tenant, string $subjectType, int $subjectId): ?string
    {
        $base = $tenant->cabinetAdminUrl();
        if ($base === null) {
            return null;
        }

        if (! $this->subjectExistsForTenant($tenant, $subjectType, $subjectId)) {
            return null;
        }

        $path = match ($subjectType) {
            class_basename(CrmRequest::class) => '/crm-requests/'.$subjectId,
            class_basename(Lead::class) => '/leads/'.$subjectId,
            class_basename(Booking::class) => '/bookings/'.$subjectId,
            default => null,
        };

        if ($path === null) {
            return null;
        }

        return rtrim($base, '/').$path;
    }

    private function subjectExistsForTenant(Tenant $tenant, string $subjectType, int $subjectId): bool
    {
        $tenantId = (int) $tenant->id;

        return match ($subjectType) {
            class_basename(CrmRequest::class) => CrmRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($subjectId)
                ->exists(),
            class_basename(Lead::class) => Lead::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($subjectId)
                ->exists(),
            class_basename(Booking::class) => Booking::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($subjectId)
                ->exists(),
            default => false,
        };
    }
}
