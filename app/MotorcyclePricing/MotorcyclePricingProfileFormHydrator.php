<?php

declare(strict_types=1);

namespace App\MotorcyclePricing;

use App\Filament\Tenant\Support\TenantMoneyForms;
use App\Money\MoneyBindingRegistry;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

/**
 * Converts between DB pricing profile JSON and flat Filament/Livewire form state (major amounts in repeater).
 */
final class MotorcyclePricingProfileFormHydrator
{
    /**
     * Legacy form/JSON used `tariff`; canonical value is `secondary_tariff`.
     */
    public static function normalizeCardSecondaryMode(string $mode): string
    {
        return $mode === 'tariff' ? 'secondary_tariff' : $mode;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public static function profileToFlatForm(array $profile): array
    {
        $currency = (string) ($profile['currency'] ?? MotorcyclePricingSchema::DEFAULT_CURRENCY);
        $tariffs = is_array($profile['tariffs'] ?? null) ? $profile['tariffs'] : [];
        $display = is_array($profile['display'] ?? null) ? $profile['display'] : [];
        $fin = is_array($profile['financial_terms'] ?? null) ? $profile['financial_terms'] : [];

        $rows = [];
        foreach (self::sortProfileTariffsForForm($tariffs) as $t) {
            if (! is_array($t)) {
                continue;
            }
            $minor = isset($t['amount_minor']) ? (int) $t['amount_minor'] : null;
            $rows[] = [
                'id' => (string) ($t['id'] ?? (string) Str::uuid()),
                'label' => (string) ($t['label'] ?? ''),
                'kind' => (string) ($t['kind'] ?? TariffKind::FixedPerDay->value),
                'amount_major' => $minor !== null ? PricingMinorMoney::minorToMajor($minor) : null,
                'block_hours' => isset($t['block_hours']) ? (int) $t['block_hours'] : 24,
                'note' => (string) ($t['note'] ?? ''),
                'applicability_mode' => (string) (is_array($t['applicability'] ?? null) ? ($t['applicability']['mode'] ?? ApplicabilityMode::Always->value) : ApplicabilityMode::Always->value),
                'min_days' => isset($t['applicability']['min_days']) ? (int) $t['applicability']['min_days'] : 1,
                'max_days' => isset($t['applicability']['max_days']) ? (int) $t['applicability']['max_days'] : 3,
                'show_on_card' => (bool) (is_array($t['visibility'] ?? null) ? ($t['visibility']['show_on_card'] ?? false) : false),
                'show_on_detail' => (bool) (is_array($t['visibility'] ?? null) ? ($t['visibility']['show_on_detail'] ?? true) : true),
                'show_in_quote' => (bool) (is_array($t['visibility'] ?? null) ? ($t['visibility']['show_in_quote'] ?? true) : true),
                'catalog_day_unit' => TariffCatalogDayUnit::fromProfile($t['catalog_day_unit'] ?? null)->value,
                'catalog_public_hint' => (string) ($t['catalog_public_hint'] ?? ''),
            ];
        }

        if ($rows === []) {
            $rows[] = self::defaultTariffRow();
        }

        $preferredCardTariffId = (string) ($display['card_primary_tariff_id'] ?? '');
        self::dedupeShowOnCardForFormRows($rows, $preferredCardTariffId);

        $depMinor = $fin['deposit_amount_minor'] ?? null;
        $preMinor = $fin['prepayment_amount_minor'] ?? null;

        return [
            'currency' => $currency,
            'tariffs' => $rows,
            'card_primary_tariff_id' => self::coalesceCardPrimaryTariffId((string) ($display['card_primary_tariff_id'] ?? ''), $rows),
            'card_secondary_mode' => self::normalizeCardSecondaryMode((string) ($display['card_secondary_mode'] ?? 'none')),
            'card_secondary_text' => isset($display['card_secondary_text']) ? (string) $display['card_secondary_text'] : '',
            'card_secondary_tariff_id' => isset($display['card_secondary_tariff_id']) ? (string) $display['card_secondary_tariff_id'] : '',
            'detail_tariffs_limit' => isset($display['detail_tariffs_limit']) && $display['detail_tariffs_limit'] !== null
                ? (int) $display['detail_tariffs_limit']
                : null,
            'deposit_amount' => $depMinor !== null && (int) $depMinor > 0 ? PricingMinorMoney::minorToMajor((int) $depMinor) : null,
            'prepayment_amount' => $preMinor !== null && (int) $preMinor > 0 ? PricingMinorMoney::minorToMajor((int) $preMinor) : null,
            'catalog_price_note' => isset($fin['catalog_price_note']) ? (string) $fin['catalog_price_note'] : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    public static function flatFormToProfile(array $flat): array
    {
        $currency = (string) ($flat['currency'] ?? MotorcyclePricingSchema::DEFAULT_CURRENCY);
        $rawTariffs = is_array($flat['tariffs'] ?? null) ? $flat['tariffs'] : [];
        $rawTariffs = array_values($rawTariffs);
        $preferredCardTariffId = (string) ($flat['card_primary_tariff_id'] ?? '');
        self::dedupeShowOnCardForFormRows($rawTariffs, $preferredCardTariffId);

        $tariffs = [];
        $seenIds = [];
        foreach ($rawTariffs as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' || isset($seenIds[$id])) {
                $id = (string) Str::uuid();
            }
            $seenIds[$id] = true;

            $kind = TariffKind::tryFrom((string) ($row['kind'] ?? '')) ?? TariffKind::FixedPerDay;
            $mode = ApplicabilityMode::tryFrom((string) ($row['applicability_mode'] ?? '')) ?? ApplicabilityMode::Always;

            $amountMajor = isset($row['amount_major']) && $row['amount_major'] !== '' && $row['amount_major'] !== null
                ? (int) $row['amount_major']
                : null;
            $amountMinor = null;
            if ($amountMajor !== null && $amountMajor >= 0 && ! in_array($kind, [TariffKind::OnRequest, TariffKind::Informational], true)) {
                $amountMinor = PricingMinorMoney::majorToMinor($amountMajor);
            }

            $applicability = ['mode' => $mode->value];
            if ($mode === ApplicabilityMode::DurationRangeDays) {
                $applicability['min_days'] = max(1, (int) ($row['min_days'] ?? 1));
                $applicability['max_days'] = max($applicability['min_days'], (int) ($row['max_days'] ?? $applicability['min_days']));
            } elseif ($mode === ApplicabilityMode::DurationMinDays) {
                $applicability['min_days'] = max(1, (int) ($row['min_days'] ?? 1));
            }

            $order = MotorcyclePricingSchema::orderValueForIndex(count($tariffs));

            $t = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? ''),
                'kind' => $kind->value,
                'applicability' => $applicability,
                'visibility' => [
                    'show_on_card' => (bool) ($row['show_on_card'] ?? false),
                    'show_on_detail' => (bool) ($row['show_on_detail'] ?? true),
                    'show_in_quote' => (bool) ($row['show_in_quote'] ?? true),
                ],
                'priority' => $order,
                'sort_order' => $order,
            ];
            if ($amountMinor !== null) {
                $t['amount_minor'] = $amountMinor;
            }
            if ($kind === TariffKind::FixedPerHourBlock) {
                $t['block_hours'] = max(1, (int) ($row['block_hours'] ?? 24));
            }
            if ($kind === TariffKind::OnRequest) {
                $t['note'] = (string) ($row['note'] ?? '');
            }
            foreach (self::catalogDisplayFieldsForProfile($kind, $row) as $ck => $cv) {
                $t[$ck] = $cv;
            }
            $tariffs[] = $t;
        }

        $primary = self::coalesceCardPrimaryTariffId((string) ($flat['card_primary_tariff_id'] ?? ''), $tariffs);
        $secondaryMode = self::normalizeCardSecondaryMode((string) ($flat['card_secondary_mode'] ?? 'none'));
        $secondaryTariffId = (string) ($flat['card_secondary_tariff_id'] ?? '');
        if ($secondaryMode !== 'secondary_tariff') {
            $secondaryTariffId = '';
        }

        $dep = $flat['deposit_amount'] ?? null;
        $pre = $flat['prepayment_amount'] ?? null;

        return [
            'schema_version' => MotorcyclePricingSchema::PROFILE_VERSION,
            'pricing_mode' => 'advanced',
            'currency' => $currency,
            'tariffs' => $tariffs,
            'display' => [
                'card_primary_tariff_id' => $primary !== '' ? $primary : null,
                'card_secondary_mode' => $secondaryMode,
                'card_secondary_text' => (string) ($flat['card_secondary_text'] ?? ''),
                'card_secondary_tariff_id' => $secondaryTariffId !== '' ? $secondaryTariffId : null,
                'detail_tariffs_limit' => isset($flat['detail_tariffs_limit']) && $flat['detail_tariffs_limit'] !== '' && $flat['detail_tariffs_limit'] !== null
                    ? (int) $flat['detail_tariffs_limit']
                    : null,
            ],
            'financial_terms' => [
                'deposit_amount_minor' => $dep !== null && $dep !== '' ? PricingMinorMoney::majorToMinor((int) $dep) : null,
                'prepayment_amount_minor' => $pre !== null && $pre !== '' ? PricingMinorMoney::majorToMinor((int) $pre) : null,
                'catalog_price_note' => trim((string) ($flat['catalog_price_note'] ?? '')) ?: null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultTariffRow(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'label' => 'Сутки',
            'kind' => TariffKind::FixedPerDay->value,
            'amount_major' => 0,
            'block_hours' => 24,
            'note' => '',
            'applicability_mode' => ApplicabilityMode::Always->value,
            'min_days' => 1,
            'max_days' => 3,
            'show_on_card' => true,
            'show_on_detail' => true,
            'show_in_quote' => true,
            'catalog_day_unit' => TariffCatalogDayUnit::FullDay->value,
            'catalog_public_hint' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private static function catalogDisplayFieldsForProfile(TariffKind $kind, array $row): array
    {
        $out = [];
        if ($kind === TariffKind::FixedPerDay) {
            $unit = TariffCatalogDayUnit::tryFrom((string) ($row['catalog_day_unit'] ?? ''))
                ?? TariffCatalogDayUnit::FullDay;
            $out['catalog_day_unit'] = $unit->value;
        }
        if (in_array($kind, [
            TariffKind::FixedPerDay,
            TariffKind::FixedPerRental,
            TariffKind::FixedPerHourBlock,
            TariffKind::Informational,
        ], true)) {
            $hint = trim((string) ($row['catalog_public_hint'] ?? ''));
            if ($hint !== '') {
                $out['catalog_public_hint'] = mb_substr($hint, 0, 80);
            }
        }

        return $out;
    }

    /**
     * Stable ordering for loading legacy / unsorted profile JSON into the repeater (matches resolver tie-break intent).
     *
     * @param  list<mixed>  $tariffs
     * @return list<array<string, mixed>>
     */
    private static function sortProfileTariffsForForm(array $tariffs): array
    {
        $wrapped = [];
        foreach ($tariffs as $origIdx => $t) {
            if (! is_array($t)) {
                continue;
            }
            $wrapped[] = ['orig' => (int) $origIdx, 't' => $t];
        }

        usort($wrapped, static function (array $a, array $b): int {
            $ta = $a['t'];
            $tb = $b['t'];
            $soA = isset($ta['sort_order']) ? (int) $ta['sort_order'] : 1_000_000;
            $soB = isset($tb['sort_order']) ? (int) $tb['sort_order'] : 1_000_000;
            if ($soA !== $soB) {
                return $soA <=> $soB;
            }
            $prA = isset($ta['priority']) ? (int) $ta['priority'] : 1_000_000;
            $prB = isset($tb['priority']) ? (int) $tb['priority'] : 1_000_000;
            if ($prA !== $prB) {
                return $prA <=> $prB;
            }

            return $a['orig'] <=> $b['orig'];
        });

        $out = [];
        foreach ($wrapped as $item) {
            $t = $item['t'];
            if (is_array($t)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * Если id основного тарифа на карточке не совпадает ни с одной строкой — подставляем первый непустой id из списка.
     *
     * @param  list<array<string, mixed>>  $tariffs
     */
    private static function coalesceCardPrimaryTariffId(string $primary, array $tariffs): string
    {
        if ($tariffs === []) {
            return '';
        }
        $validIds = [];
        foreach ($tariffs as $t) {
            if (! is_array($t)) {
                continue;
            }
            $id = (string) ($t['id'] ?? '');
            if ($id !== '') {
                $validIds[$id] = true;
            }
        }
        if ($primary !== '' && isset($validIds[$primary])) {
            return $primary;
        }
        foreach ($tariffs as $t) {
            if (! is_array($t)) {
                continue;
            }
            $id = (string) ($t['id'] ?? '');
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * Не более одной строки с «Показывать в каталоге»: при нескольких true оставляем строку,
     * совпадающую с основным тарифом на карточке (если она среди отмеченных), иначе первую в списке.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private static function dedupeShowOnCardForFormRows(array &$rows, string $preferredTariffId): void
    {
        $indexes = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['show_on_card'])) {
                $indexes[] = (int) $i;
            }
        }
        if (count($indexes) <= 1) {
            return;
        }
        $keepIdx = $indexes[0];
        if ($preferredTariffId !== '') {
            foreach ($indexes as $i) {
                if (! is_array($rows[$i] ?? null)) {
                    continue;
                }
                if ((string) ($rows[$i]['id'] ?? '') === $preferredTariffId) {
                    $keepIdx = $i;
                    break;
                }
            }
        }
        foreach ($indexes as $i) {
            if ($i !== $keepIdx && is_array($rows[$i] ?? null)) {
                $rows[$i]['show_on_card'] = false;
            }
        }
    }

    /**
     * Money input for profile financial fields (storage integer major units).
     */
    public static function profileMoneyInput(string $name, string $label): TextInput
    {
        return TenantMoneyForms::moneyTextInput(
            $name,
            MoneyBindingRegistry::MOTORCYCLE_PRICE_PER_DAY,
            $label,
            required: false,
            nullableStorage: true,
        );
    }
}
