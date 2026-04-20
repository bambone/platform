<?php

declare(strict_types=1);

namespace Tests\Unit\MotorcyclePricing;

use App\MotorcyclePricing\MotorcyclePricingProfileFormHydrator;
use App\MotorcyclePricing\MotorcycleTariffResolver;
use App\MotorcyclePricing\TariffKind;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MotorcycleTariffResolverTest extends TestCase
{
    #[Test]
    public function it_prefers_narrow_duration_range_over_always(): void
    {
        $resolver = new MotorcycleTariffResolver;
        $tariffs = [
            [
                'id' => 'always',
                'kind' => TariffKind::FixedPerDay->value,
                'amount_minor' => 10_000,
                'applicability' => ['mode' => 'always'],
                'priority' => 500,
            ],
            [
                'id' => 'range',
                'kind' => TariffKind::FixedPerDay->value,
                'amount_minor' => 8_000,
                'applicability' => ['mode' => 'duration_range_days', 'min_days' => 2, 'max_days' => 3],
                'priority' => 500,
            ],
        ];

        $out = $resolver->resolveForAutoQuote($tariffs, 2);

        $this->assertFalse($out['conflict']);
        $this->assertSame('range', $out['tariff']['id'] ?? null);
    }

    #[Test]
    public function it_prefers_first_row_in_profile_order_when_applicability_is_equal(): void
    {
        $idA = 'tariff-row-a';
        $idB = 'tariff-row-b';

        $baseFlat = [
            'currency' => 'RUB',
            'tariffs' => [
                array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
                    'id' => $idA,
                    'label' => 'A',
                    'amount_major' => 1000,
                ]),
                array_merge(MotorcyclePricingProfileFormHydrator::defaultTariffRow(), [
                    'id' => $idB,
                    'label' => 'B',
                    'amount_major' => 2000,
                ]),
            ],
            'card_primary_tariff_id' => $idA,
            'card_secondary_mode' => 'none',
            'card_secondary_text' => '',
            'card_secondary_tariff_id' => '',
            'detail_tariffs_limit' => null,
            'deposit_amount' => null,
            'prepayment_amount' => null,
            'catalog_price_note' => '',
        ];

        $resolver = new MotorcycleTariffResolver;

        $profile = MotorcyclePricingProfileFormHydrator::flatFormToProfile($baseFlat);
        $out = $resolver->resolveForAutoQuote($profile['tariffs'], 3);
        $this->assertFalse($out['conflict']);
        $this->assertSame($idA, $out['tariff']['id'] ?? null);

        $baseFlat['tariffs'] = array_reverse($baseFlat['tariffs']);
        $profile = MotorcyclePricingProfileFormHydrator::flatFormToProfile($baseFlat);
        $out = $resolver->resolveForAutoQuote($profile['tariffs'], 3);
        $this->assertFalse($out['conflict']);
        $this->assertSame($idB, $out['tariff']['id'] ?? null);
    }

    #[Test]
    public function it_skips_tariffs_with_show_in_quote_disabled(): void
    {
        $resolver = new MotorcycleTariffResolver;
        $tariffs = [
            [
                'id' => 'hidden_from_quote',
                'kind' => TariffKind::FixedPerDay->value,
                'amount_minor' => 10_000,
                'applicability' => ['mode' => 'always'],
                'priority' => 500,
                'visibility' => [
                    'show_on_card' => true,
                    'show_on_detail' => true,
                    'show_in_quote' => false,
                ],
            ],
            [
                'id' => 'quoted',
                'kind' => TariffKind::FixedPerDay->value,
                'amount_minor' => 12_000,
                'applicability' => ['mode' => 'always'],
                'priority' => 501,
                'visibility' => [
                    'show_on_card' => true,
                    'show_on_detail' => true,
                    'show_in_quote' => true,
                ],
            ],
        ];

        $out = $resolver->resolveForAutoQuote($tariffs, 2);

        $this->assertFalse($out['conflict']);
        $this->assertSame('quoted', $out['tariff']['id'] ?? null);
    }

    #[Test]
    public function it_reports_conflict_when_two_always_tariffs_tie_on_priority(): void
    {
        $resolver = new MotorcycleTariffResolver;
        $tariffs = [
            [
                'id' => 'a',
                'kind' => TariffKind::FixedPerDay->value,
                'amount_minor' => 10_000,
                'applicability' => ['mode' => 'always'],
                'priority' => 100,
            ],
            [
                'id' => 'b',
                'kind' => TariffKind::FixedPerDay->value,
                'amount_minor' => 11_000,
                'applicability' => ['mode' => 'always'],
                'priority' => 100,
            ],
        ];

        $out = $resolver->resolveForAutoQuote($tariffs, 3);

        $this->assertTrue($out['conflict']);
        $this->assertNull($out['tariff']);
        $this->assertSame('unresolved_tariff_tie', $out['reason']);
    }
}
