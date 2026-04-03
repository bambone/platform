<?php

namespace App\Tenant\StorageQuota;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantStorageQuota;
use App\Models\TenantStorageQuotaEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantStorageQuotaService
{
    public static function withoutQuotaEnforcement(callable $callback): mixed
    {
        TenantStorageQuotaEnforcementContext::enterBypass();
        try {
            return $callback();
        } finally {
            TenantStorageQuotaEnforcementContext::leaveBypass();
        }
    }

    public static function isQuotaEnforcementActive(): bool
    {
        return ! TenantStorageQuotaEnforcementContext::isBypassed();
    }

    public function defaultBaseQuotaBytes(): int
    {
        $fromDb = PlatformSetting::get('tenant_storage.default_base_quota_bytes', null);
        if ($fromDb !== null && is_numeric($fromDb)) {
            return max(0, (int) $fromDb);
        }

        return max(0, (int) config('tenant_storage_quotas.default_base_quota_bytes', 100 * 1024 * 1024));
    }

    public function defaultWarningThresholdPercent(): int
    {
        $v = PlatformSetting::get('tenant_storage.default_warning_threshold_percent', null);

        return max(1, min(99, (int) ($v ?? config('tenant_storage_quotas.default_warning_threshold_percent', 20))));
    }

    public function defaultCriticalThresholdPercent(): int
    {
        $v = PlatformSetting::get('tenant_storage.default_critical_threshold_percent', null);

        return max(1, min(99, (int) ($v ?? config('tenant_storage_quotas.default_critical_threshold_percent', 10))));
    }

    public function defaultHardStopEnabled(): bool
    {
        $v = PlatformSetting::get('tenant_storage.default_hard_stop_enabled', null);
        if ($v !== null) {
            return (bool) $v;
        }

        return (bool) config('tenant_storage_quotas.default_hard_stop_enabled', true);
    }

    public function ensureQuotaRecord(Tenant $tenant): TenantStorageQuota
    {
        return DB::transaction(function () use ($tenant): TenantStorageQuota {
            $existing = TenantStorageQuota::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            return TenantStorageQuota::query()->create([
                'tenant_id' => $tenant->id,
                'base_quota_bytes' => $this->defaultBaseQuotaBytes(),
                'extra_quota_bytes' => 0,
                'used_bytes' => 0,
                'reserved_bytes' => 0,
                'status' => TenantStorageQuotaStatus::Ok->value,
                'warning_threshold_percent' => $this->defaultWarningThresholdPercent(),
                'critical_threshold_percent' => $this->defaultCriticalThresholdPercent(),
                'hard_stop_enabled' => $this->defaultHardStopEnabled(),
            ]);
        });
    }

    public function forTenant(Tenant $tenant): TenantStorageQuotaData
    {
        $q = $tenant->storageQuota ?? $this->ensureQuotaRecord($tenant);
        $q->refresh();

        return $this->toData($q);
    }

    public function toData(TenantStorageQuota $q): TenantStorageQuotaData
    {
        $effective = $q->effective_quota_bytes;
        $used = max(0, (int) $q->used_bytes);
        $free = max(0, $effective - $used);
        $usedPercent = $effective > 0 ? round(($used / $effective) * 100, 2) : ($used > 0 ? 100.0 : 0.0);
        $freePercent = $effective > 0 ? round(($free / $effective) * 100, 2) : 0.0;

        $staleHours = (int) config('tenant_storage_quotas.stale_sync_hours', 72);
        $syncedAt = $q->last_synced_from_storage_at;
        $isStale = $syncedAt === null || $syncedAt->lt(now()->subHours($staleHours));

        return new TenantStorageQuotaData(
            tenantId: (int) $q->tenant_id,
            baseQuotaBytes: (int) $q->base_quota_bytes,
            extraQuotaBytes: (int) $q->extra_quota_bytes,
            effectiveQuotaBytes: $effective,
            usedBytes: $used,
            freeBytes: $free,
            usedPercent: $usedPercent,
            freePercent: $freePercent,
            status: $this->computeStatus($effective, $used, (int) $q->warning_threshold_percent, (int) $q->critical_threshold_percent),
            hardStopEnabled: (bool) $q->hard_stop_enabled,
            lastRecalculatedAt: $q->last_recalculated_at,
            lastSyncedFromStorageAt: $q->last_synced_from_storage_at,
            isStaleSync: $isStale,
            lastScanSummary: $q->last_scan_summary_json,
            lastSyncErrorAt: $q->last_sync_error_at,
            lastSyncErrorMessage: $q->last_sync_error_message,
            warningThresholdPercent: (int) $q->warning_threshold_percent,
            criticalThresholdPercent: (int) $q->critical_threshold_percent,
        );
    }

    public function computeStatus(int $effectiveQuotaBytes, int $usedBytes, int $warningThresholdPercent, int $criticalThresholdPercent): TenantStorageQuotaStatus
    {
        if ($effectiveQuotaBytes <= 0) {
            return TenantStorageQuotaStatus::Exceeded;
        }
        $free = $effectiveQuotaBytes - $usedBytes;
        if ($free <= 0) {
            return TenantStorageQuotaStatus::Exceeded;
        }
        $freePercent = ($free / $effectiveQuotaBytes) * 100;
        if ($freePercent <= $criticalThresholdPercent) {
            return TenantStorageQuotaStatus::Critical10;
        }
        if ($freePercent <= $warningThresholdPercent) {
            return TenantStorageQuotaStatus::Warning20;
        }

        return TenantStorageQuotaStatus::Ok;
    }

    public function recalculateStatus(TenantStorageQuota $quota): void
    {
        $effective = $quota->effective_quota_bytes;
        $newStatus = $this->computeStatus(
            $effective,
            (int) $quota->used_bytes,
            (int) $quota->warning_threshold_percent,
            (int) $quota->critical_threshold_percent,
        );
        $old = TenantStorageQuotaStatus::tryFrom((string) $quota->status) ?? TenantStorageQuotaStatus::Ok;
        if ($old === $newStatus) {
            $quota->status = $newStatus->value;
            $quota->save();

            return;
        }
        $quota->status = $newStatus->value;
        $quota->last_recalculated_at = now();
        $quota->save();
        $this->recordStatusChangeEvents((int) $quota->tenant_id, $old, $newStatus);
    }

    public function canStoreBytes(Tenant $tenant, int $incomingBytes): QuotaCheckResult
    {
        $incomingBytes = max(0, $incomingBytes);
        $q = $tenant->storageQuota ?? $this->ensureQuotaRecord($tenant);
        $effective = $q->effective_quota_bytes;
        $used = (int) $q->used_bytes;
        $free = max(0, $effective - $used);
        $freePercent = $effective > 0 ? ($free / $effective) * 100 : 0.0;

        if (! $q->hard_stop_enabled) {
            return new QuotaCheckResult(true, null, false, $used, $effective, $free, $freePercent);
        }

        if ($incomingBytes === 0) {
            return new QuotaCheckResult(true, null, false, $used, $effective, $free, $freePercent);
        }

        if ($used + $incomingBytes > $effective) {
            return new QuotaCheckResult(
                false,
                'quota_exceeded',
                true,
                $used,
                $effective,
                $free,
                $freePercent,
            );
        }

        return new QuotaCheckResult(true, null, false, $used, $effective, $free, $freePercent);
    }

    public function assertCanStoreBytes(Tenant $tenant, int $incomingBytes, ?string $context = null): void
    {
        if (! self::isQuotaEnforcementActive()) {
            return;
        }
        $check = $this->canStoreBytes($tenant, $incomingBytes);
        if ($check->allowed) {
            return;
        }
        $this->recordEvent(
            (int) $tenant->id,
            TenantStorageQuotaEventType::UploadBlockedQuotaExceeded,
            [
                'context' => $context,
                'incoming_bytes' => $incomingBytes,
                'used_bytes' => $check->usedBytes,
                'quota_bytes' => $check->quotaBytes,
                'free_bytes' => $check->freeBytes,
                'free_percent' => $check->freePercent,
            ],
        );
        throw new StorageQuotaExceededException(StorageQuotaExceededException::defaultMessage(), $context, $check);
    }

    /**
     * @param  callable(TenantStorageQuota): void  $callback
     */
    public function withLockedQuota(Tenant $tenant, callable $callback): void
    {
        DB::transaction(function () use ($tenant, $callback): void {
            $quota = TenantStorageQuota::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();
            if ($quota === null) {
                $this->ensureQuotaRecord($tenant);
                $quota = TenantStorageQuota::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $callback($quota);
        });
    }

    public function increaseUsage(Tenant $tenant, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $this->withLockedQuota($tenant, function (TenantStorageQuota $quota) use ($bytes): void {
            $quota->used_bytes = (int) $quota->used_bytes + $bytes;
            $quota->last_recalculated_at = now();
            $quota->save();
            $this->recalculateStatus($quota);
        });
    }

    public function decreaseUsage(Tenant $tenant, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $this->withLockedQuota($tenant, function (TenantStorageQuota $quota) use ($bytes): void {
            $quota->used_bytes = max(0, (int) $quota->used_bytes - $bytes);
            $quota->last_recalculated_at = now();
            $quota->save();
            $this->recalculateStatus($quota);
        });
    }

    public function applyUsageDelta(Tenant $tenant, int $deltaBytes): void
    {
        if ($deltaBytes === 0) {
            return;
        }
        if ($deltaBytes > 0) {
            $this->increaseUsage($tenant, $deltaBytes);
        } else {
            $this->decreaseUsage($tenant, abs($deltaBytes));
        }
    }

    public function setExtraQuotaBytes(Tenant $tenant, int $extraBytes, ?int $actorUserId = null): TenantStorageQuota
    {
        $extraBytes = max(0, $extraBytes);

        return DB::transaction(function () use ($tenant, $extraBytes, $actorUserId): TenantStorageQuota {
            $quota = TenantStorageQuota::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();
            if ($quota === null) {
                $this->ensureQuotaRecord($tenant);
                $quota = TenantStorageQuota::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $before = (int) $quota->extra_quota_bytes;
            $quota->extra_quota_bytes = $extraBytes;
            $quota->last_recalculated_at = now();
            $quota->save();
            $this->recalculateStatus($quota);
            $this->recordEvent((int) $tenant->id, TenantStorageQuotaEventType::QuotaChanged, [
                'field' => 'extra_quota_bytes',
                'before' => $before,
                'after' => $extraBytes,
                'effective_after' => $quota->effective_quota_bytes,
            ], $actorUserId);

            return $quota->fresh();
        });
    }

    public function setBaseQuotaBytes(Tenant $tenant, int $baseBytes, ?int $actorUserId = null): TenantStorageQuota
    {
        $baseBytes = max(0, $baseBytes);

        return DB::transaction(function () use ($tenant, $baseBytes, $actorUserId): TenantStorageQuota {
            $quota = TenantStorageQuota::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();
            if ($quota === null) {
                $this->ensureQuotaRecord($tenant);
                $quota = TenantStorageQuota::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $before = (int) $quota->base_quota_bytes;
            $quota->base_quota_bytes = $baseBytes;
            $quota->last_recalculated_at = now();
            $quota->save();
            $this->recalculateStatus($quota);
            $this->recordEvent((int) $tenant->id, TenantStorageQuotaEventType::QuotaChanged, [
                'field' => 'base_quota_bytes',
                'before' => $before,
                'after' => $baseBytes,
                'effective_after' => $quota->effective_quota_bytes,
            ], $actorUserId);

            return $quota->fresh();
        });
    }

    public function setStoragePackageLabel(Tenant $tenant, ?string $label, ?int $actorUserId = null): TenantStorageQuota
    {
        return DB::transaction(function () use ($tenant, $label, $actorUserId): TenantStorageQuota {
            $quota = $this->ensureQuotaRecord($tenant);
            $quota = TenantStorageQuota::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();
            $quota->storage_package_label = $label;
            $quota->save();
            $this->recordEvent((int) $tenant->id, TenantStorageQuotaEventType::QuotaChanged, [
                'field' => 'storage_package_label',
                'after' => $label,
            ], $actorUserId);

            return $quota->fresh();
        });
    }

    public function markSyncSuccess(
        Tenant $tenant,
        int $usedBytes,
        array $scanSummary,
    ): TenantStorageQuota {
        return DB::transaction(function () use ($tenant, $usedBytes, $scanSummary): TenantStorageQuota {
            $quota = TenantStorageQuota::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();
            if ($quota === null) {
                $this->ensureQuotaRecord($tenant);
                $quota = TenantStorageQuota::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $oldStatus = TenantStorageQuotaStatus::tryFrom((string) $quota->status) ?? TenantStorageQuotaStatus::Ok;
            $quota->used_bytes = max(0, $usedBytes);
            $quota->last_scan_summary_json = $scanSummary;
            $quota->last_synced_from_storage_at = now();
            $quota->last_sync_error_at = null;
            $quota->last_sync_error_message = null;
            $quota->last_recalculated_at = now();
            $quota->save();
            $this->recalculateStatus($quota);
            $quota->refresh();
            $newStatus = TenantStorageQuotaStatus::tryFrom((string) $quota->status) ?? TenantStorageQuotaStatus::Ok;
            $this->recordEvent((int) $tenant->id, TenantStorageQuotaEventType::Recalculated, [
                'used_bytes' => $usedBytes,
                'summary' => $scanSummary,
                'status_before' => $oldStatus->value,
                'status_after' => $newStatus->value,
            ]);

            return $quota;
        });
    }

    public function markSyncFailure(Tenant $tenant, string $message): TenantStorageQuota
    {
        $quota = $this->ensureQuotaRecord($tenant);
        $quota->last_sync_error_at = now();
        $quota->last_sync_error_message = $message;
        $quota->save();

        return $quota->fresh();
    }

    public function recordEvent(int $tenantId, TenantStorageQuotaEventType $type, ?array $payload = null, ?int $createdBy = null): void
    {
        TenantStorageQuotaEvent::query()->create([
            'tenant_id' => $tenantId,
            'type' => $type->value,
            'payload' => $payload,
            'created_by' => $createdBy ?? (Auth::id() ? (int) Auth::id() : null),
        ]);
    }

    private function recordStatusChangeEvents(int $tenantId, TenantStorageQuotaStatus $from, TenantStorageQuotaStatus $to): void
    {
        if ($from === $to) {
            return;
        }
        $type = match ($to) {
            TenantStorageQuotaStatus::Warning20 => TenantStorageQuotaEventType::UsageWarning20,
            TenantStorageQuotaStatus::Critical10 => TenantStorageQuotaEventType::UsageCritical10,
            TenantStorageQuotaStatus::Exceeded => TenantStorageQuotaEventType::UsageExceeded,
            TenantStorageQuotaStatus::Ok => TenantStorageQuotaEventType::UsageBackToNormal,
        };
        $this->recordEvent($tenantId, $type, [
            'from' => $from->value,
            'to' => $to->value,
        ]);
    }
}
