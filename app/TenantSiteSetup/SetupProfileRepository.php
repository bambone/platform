<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\TenantSetting;

/**
 * setup.profile JSON (answers + schema version). Does not overwrite tenant business data.
 */
final class SetupProfileRepository
{
    public const SETTING_KEY = 'setup.profile';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'schema_version' => $this->schemaVersion(),
            'business_focus' => '',
            'primary_goal' => '',
            /** MVP: crm_only | slot_booking | mixed — см. TenantOnboardingBranchId */
            'desired_branch' => '',
            'additional_notes' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $tenantId): array
    {
        $raw = TenantSetting::getForTenant($tenantId, self::SETTING_KEY, []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * Defaults merged over stored answers (UI / journey shape).
     *
     * @return array<string, mixed>
     */
    public function getMerged(int $tenantId): array
    {
        return array_merge($this->defaults(), $this->get($tenantId));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(int $tenantId, array $data): void
    {
        TenantSetting::setForTenant($tenantId, self::SETTING_KEY, $data, 'json');
    }

    public function schemaVersion(): int
    {
        return 2;
    }
}
