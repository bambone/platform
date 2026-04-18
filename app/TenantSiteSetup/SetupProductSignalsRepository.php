<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\TenantSetting;

/**
 * Продуктовые сигналы (KB / backlog): не смешивать с боевыми настройками сайта.
 *
 * Ключ: {@see self::SETTING_KEY} → JSON. Структура `calendar_signals` — см. план и docs/architecture/tenant-onboarding-question-mapping.md
 *
 * @phpstan-type CalendarSignals array{
 *   uses_external_calendars: bool|null,
 *   providers: list<string>,
 *   other_provider_text: string,
 *   use_cases: list<string>,
 *   desired_sync_mode: string,
 *   criticality: string,
 *   constraints: list<string>,
 *   notes: string,
 * }
 */
final class SetupProductSignalsRepository
{
    public const SETTING_KEY = 'setup.product_signals';

    /**
     * @return array{schema_version: int, calendar_signals: CalendarSignals}
     */
    public function defaults(): array
    {
        return [
            'schema_version' => $this->schemaVersion(),
            'calendar_signals' => $this->defaultCalendarSignals(),
        ];
    }

    /**
     * @return CalendarSignals
     */
    public function defaultCalendarSignals(): array
    {
        return [
            'uses_external_calendars' => null,
            'providers' => [],
            'other_provider_text' => '',
            'use_cases' => [],
            'desired_sync_mode' => '',
            'criticality' => '',
            'constraints' => [],
            'notes' => '',
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
        $data['schema_version'] = $this->schemaVersion();
        TenantSetting::setForTenant($tenantId, self::SETTING_KEY, $data, 'json');
    }

    public function schemaVersion(): int
    {
        return 1;
    }
}
