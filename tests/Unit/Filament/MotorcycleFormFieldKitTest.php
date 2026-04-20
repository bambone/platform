<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Enums\MotorcycleLocationMode;
use App\Filament\Tenant\Resources\MotorcycleResource\Form\MotorcycleFormFieldKit;
use App\MotorcyclePricing\ApplicabilityMode;
use App\MotorcyclePricing\MotorcyclePricingProfileFormHydrator;
use App\MotorcyclePricing\MotorcyclePricingSchema;
use App\MotorcyclePricing\TariffKind;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MotorcycleFormFieldKitTest extends TestCase
{
    public function test_normalize_fleet_location_clears_per_unit_when_fleet_off(): void
    {
        $data = MotorcycleFormFieldKit::normalizeFleetLocationFormState([
            'uses_fleet_units' => false,
            'location_mode' => MotorcycleLocationMode::PerUnit->value,
            'tenant_location_ids' => [1, 2],
        ]);

        $this->assertFalse($data['uses_fleet_units']);
        $this->assertSame(MotorcycleLocationMode::Everywhere->value, $data['location_mode']);
        $this->assertSame([], $data['tenant_location_ids']);
    }

    public function test_normalize_fleet_location_keeps_tenant_locations_when_selected(): void
    {
        $data = MotorcycleFormFieldKit::normalizeFleetLocationFormState([
            'uses_fleet_units' => true,
            'location_mode' => MotorcycleLocationMode::Selected->value,
            'tenant_location_ids' => [5, 7],
        ]);

        $this->assertSame([5, 7], $data['tenant_location_ids']);
    }

    public function test_merge_pricing_profile_coalesces_stale_primary_tariff_to_first_row(): void
    {
        $keepId = (string) Str::uuid();
        $staleId = (string) Str::uuid();
        $row = array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
            'id' => $keepId,
            'amount_major' => 1500,
            'label' => 'День',
        ]);

        $merged = MotorcycleFormFieldKit::mergePricingProfileIntoMotorcycleData([
            'pricing_currency' => 'RUB',
            'pricing_tariffs' => [$row],
            'pricing_card_primary_tariff_id' => $staleId,
            'pricing_card_secondary_mode' => 'none',
            'pricing_card_secondary_text' => '',
            'pricing_card_secondary_tariff_id' => '',
            'pricing_detail_tariffs_limit' => null,
            'pricing_deposit_amount' => null,
            'pricing_prepayment_amount' => null,
            'pricing_catalog_price_note' => '',
        ]);

        $display = $merged['pricing_profile_json']['display'] ?? [];
        $this->assertSame($keepId, (string) ($display['card_primary_tariff_id'] ?? ''));
    }

    public function test_parse_exclusive_show_on_card_branch_supports_nested_and_root_paths(): void
    {
        $uuid = 'a1111111-b222-4ccc-9ddd-eeeeeeeeeeee';
        $nested = MotorcycleFormFieldKit::parseExclusiveShowOnCardBranch('data.pricing_tariffs.'.$uuid.'.default.show_on_card');
        $this->assertNotNull($nested);
        $this->assertSame('data.pricing_tariffs', $nested['repeaterBase']);
        $this->assertSame($uuid, $nested['currentItemKey']);
        $this->assertSame('default', $nested['inItemSuffix']);

        $flat = MotorcycleFormFieldKit::parseExclusiveShowOnCardBranch('pricing_tariffs.'.$uuid.'.show_on_card');
        $this->assertNotNull($flat);
        $this->assertSame('pricing_tariffs', $flat['repeaterBase']);
        $this->assertSame($uuid, $flat['currentItemKey']);
        $this->assertSame('', $flat['inItemSuffix']);

        $prefixed = MotorcycleFormFieldKit::parseExclusiveShowOnCardBranch('mountedSchemas.foo.data.pricing_tariffs.'.$uuid.'.show_on_card');
        $this->assertNotNull($prefixed);
        $this->assertSame('mountedSchemas.foo.data.pricing_tariffs', $prefixed['repeaterBase']);
        $this->assertSame($uuid, $prefixed['currentItemKey']);
    }

    public function test_merge_pricing_profile_sets_primary_when_single_tariff_and_empty(): void
    {
        $keepId = (string) Str::uuid();
        $row = array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
            'id' => $keepId,
            'amount_major' => 1500,
            'label' => 'День',
        ]);

        $merged = MotorcycleFormFieldKit::mergePricingProfileIntoMotorcycleData([
            'pricing_currency' => 'RUB',
            'pricing_tariffs' => [$row],
            'pricing_card_primary_tariff_id' => '',
            'pricing_card_secondary_mode' => 'none',
            'pricing_card_secondary_text' => '',
            'pricing_card_secondary_tariff_id' => '',
            'pricing_detail_tariffs_limit' => null,
            'pricing_deposit_amount' => null,
            'pricing_prepayment_amount' => null,
            'pricing_catalog_price_note' => '',
        ]);

        $profile = $merged['pricing_profile_json'];
        $this->assertIsArray($profile);
        $display = $profile['display'] ?? [];
        $this->assertSame($keepId, (string) ($display['card_primary_tariff_id'] ?? ''));
    }

    public function test_merge_pricing_profile_assigns_priority_from_row_order_via_schema_helper(): void
    {
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $id3 = (string) Str::uuid();

        $rows = [
            array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
                'id' => $id1,
                'label' => 'Один',
                'amount_major' => 1000,
            ]),
            array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
                'id' => $id2,
                'label' => 'Два',
                'amount_major' => 2000,
            ]),
            array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
                'id' => $id3,
                'label' => 'Три',
                'amount_major' => 3000,
            ]),
        ];

        $merged = MotorcycleFormFieldKit::mergePricingProfileIntoMotorcycleData([
            'pricing_currency' => 'RUB',
            'pricing_tariffs' => $rows,
            'pricing_card_primary_tariff_id' => $id1,
            'pricing_card_secondary_mode' => 'none',
            'pricing_card_secondary_text' => '',
            'pricing_card_secondary_tariff_id' => '',
            'pricing_detail_tariffs_limit' => null,
            'pricing_deposit_amount' => null,
            'pricing_prepayment_amount' => null,
            'pricing_catalog_price_note' => '',
        ]);

        $tariffs = $merged['pricing_profile_json']['tariffs'] ?? [];
        $this->assertCount(3, $tariffs);
        foreach ($tariffs as $i => $t) {
            $expected = MotorcyclePricingSchema::orderValueForIndex($i);
            $this->assertSame($expected, (int) ($t['priority'] ?? 0), 'priority idx '.$i);
            $this->assertSame($expected, (int) ($t['sort_order'] ?? 0), 'sort_order idx '.$i);
        }
    }

    public function test_merge_pricing_profile_keeps_primary_tariff_id_after_row_reorder(): void
    {
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $id3 = (string) Str::uuid();

        $row = static fn (string $id, string $label): array => array_merge(
            MotorcyclePricingProfileFormHydrator::defaultTariffRow(),
            [
                'id' => $id,
                'label' => $label,
                'amount_major' => 1000,
            ],
        );

        $originalOrder = [$row($id1, 'Первый'), $row($id2, 'Второй'), $row($id3, 'Третий')];
        $reordered = [$row($id3, 'Третий'), $row($id1, 'Первый'), $row($id2, 'Второй')];

        $merge = static function (array $tariffRows) use ($id2): array {
            return MotorcycleFormFieldKit::mergePricingProfileIntoMotorcycleData([
                'pricing_currency' => 'RUB',
                'pricing_tariffs' => $tariffRows,
                'pricing_card_primary_tariff_id' => $id2,
                'pricing_card_secondary_mode' => 'none',
                'pricing_card_secondary_text' => '',
                'pricing_card_secondary_tariff_id' => '',
                'pricing_detail_tariffs_limit' => null,
                'pricing_deposit_amount' => null,
                'pricing_prepayment_amount' => null,
                'pricing_catalog_price_note' => '',
            ]);
        };

        $displayBefore = $merge($originalOrder)['pricing_profile_json']['display'] ?? [];
        $displayAfter = $merge($reordered)['pricing_profile_json']['display'] ?? [];

        $this->assertSame($id2, (string) ($displayBefore['card_primary_tariff_id'] ?? ''));
        $this->assertSame($id2, (string) ($displayAfter['card_primary_tariff_id'] ?? ''));
    }

    public function test_format_tariff_repeater_item_label_joins_summary_parts(): void
    {
        $line = MotorcycleFormFieldKit::formatTariffRepeaterItemLabel([
            'label' => 'Будни',
            'kind' => TariffKind::FixedPerDay->value,
            'amount_major' => 9500,
            'applicability_mode' => ApplicabilityMode::Always->value,
        ], 'RUB');

        $this->assertSame('Будни · Сутки · 9 500 ₽ · Всегда', $line);
    }
}
