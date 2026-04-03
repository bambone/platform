<?php

namespace App\Models;

use App\Tenant\StorageQuota\TenantStorageQuotaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantStorageQuota extends Model
{
    protected $fillable = [
        'tenant_id',
        'base_quota_bytes',
        'extra_quota_bytes',
        'used_bytes',
        'reserved_bytes',
        'status',
        'warning_threshold_percent',
        'critical_threshold_percent',
        'hard_stop_enabled',
        'last_recalculated_at',
        'last_synced_from_storage_at',
        'last_scan_summary_json',
        'last_sync_error_at',
        'last_sync_error_message',
        'notes',
        'storage_package_label',
    ];

    protected function casts(): array
    {
        return [
            'base_quota_bytes' => 'integer',
            'extra_quota_bytes' => 'integer',
            'used_bytes' => 'integer',
            'reserved_bytes' => 'integer',
            'warning_threshold_percent' => 'integer',
            'critical_threshold_percent' => 'integer',
            'hard_stop_enabled' => 'boolean',
            'last_recalculated_at' => 'datetime',
            'last_synced_from_storage_at' => 'datetime',
            'last_scan_summary_json' => 'array',
            'last_sync_error_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TenantStorageQuotaEvent::class, 'tenant_id', 'tenant_id');
    }

    public function getEffectiveQuotaBytesAttribute(): int
    {
        return max(0, (int) $this->base_quota_bytes + (int) $this->extra_quota_bytes);
    }

    public function statusEnum(): TenantStorageQuotaStatus
    {
        return TenantStorageQuotaStatus::tryFrom((string) $this->status) ?? TenantStorageQuotaStatus::Ok;
    }
}
