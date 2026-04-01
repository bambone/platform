<?php

namespace App\Services\Analytics;

use App\Models\TenantSetting;
use App\Support\Analytics\AnalyticsSettingsData;
use Illuminate\Contracts\Auth\Authenticatable;

final class AnalyticsSettingsPersistence
{
    public const SETTING_KEY = 'integrations.analytics';

    public function __construct(
        private readonly TenantAnalyticsAuditLogger $auditLogger,
    ) {}

    public function load(int $tenantId): AnalyticsSettingsData
    {
        $raw = TenantSetting::getForTenant($tenantId, self::SETTING_KEY, null);

        return AnalyticsSettingsData::fromStorage(is_array($raw) ? $raw : null);
    }

    public function save(
        int $tenantId,
        AnalyticsSettingsData $data,
        ?Authenticatable $actor,
        ?AnalyticsSettingsData $before = null,
    ): void {
        $before ??= $this->load($tenantId);

        TenantSetting::setForTenant(
            $tenantId,
            self::SETTING_KEY,
            $data->toStorageArray(),
            'json'
        );

        $this->auditLogger->logUpdated($tenantId, $actor, $before, $data);
    }
}
