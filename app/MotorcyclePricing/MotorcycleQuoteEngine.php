<?php

declare(strict_types=1);

namespace App\MotorcyclePricing;

use App\Models\Motorcycle;

final class MotorcycleQuoteEngine
{
    public function __construct(
        private readonly MotorcyclePricingProfileLoader $loader,
        private readonly MotorcyclePricingProfileValidator $validator,
        private readonly MotorcycleTariffResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quoteForDays(Motorcycle $motorcycle, int $days): array
    {
        $profile = $this->loader->loadOrSynthesize($motorcycle);
        if ($profile === []) {
            return [
                'status' => 'invalid_profile',
                'error_code' => 'PROFILE_EMPTY',
                'message' => 'Профиль ценообразования отсутствует.',
            ];
        }

        $v = $this->validator->validate($profile);
        if ($v['validity'] === PricingProfileValidity::Invalid) {
            return [
                'status' => 'invalid_profile',
                'error_code' => 'PROFILE_INVALID',
                'message' => 'Профиль ценообразования заполнен некорректно.',
                'details' => $v['errors'],
            ];
        }

        $currency = (string) ($profile['currency'] ?? MotorcyclePricingSchema::DEFAULT_CURRENCY);
        $tariffs = is_array($profile['tariffs'] ?? null) ? $profile['tariffs'] : [];

        $resolved = $this->resolver->resolveForAutoQuote($tariffs, max(1, $days));
        if ($resolved['conflict']) {
            return [
                'status' => 'invalid_profile',
                'error_code' => 'TARIFF_CONFLICT',
                'message' => 'Не удалось однозначно выбрать тариф.',
            ];
        }

        $tariff = $resolved['tariff'];
        if ($tariff === null) {
            $onRequest = $this->firstOnRequestTariff($tariffs);
            if ($onRequest !== null) {
                return $this->onRequestPayload($onRequest, $currency, $v['warnings']);
            }

            return [
                'status' => 'invalid_profile',
                'error_code' => 'NO_AUTO_TARIFF',
                'message' => 'Нет тарифа для автоматического расчёта.',
            ];
        }

        $kind = TariffKind::tryFrom((string) ($tariff['kind'] ?? ''));
        $amountMinor = isset($tariff['amount_minor']) ? (int) $tariff['amount_minor'] : null;
        if ($kind === TariffKind::OnRequest) {
            return $this->onRequestPayload($tariff, $currency, $v['warnings']);
        }

        if ($amountMinor === null || $amountMinor < 0) {
            return [
                'status' => 'invalid_profile',
                'error_code' => 'TARIFF_AMOUNT_MISSING',
                'message' => 'У тарифа не задана сумма.',
            ];
        }

        $lines = [];
        $rentalTotalMinor = 0;
        $d = max(1, $days);

        if ($kind === TariffKind::FixedPerDay) {
            $lineTotal = $amountMinor * $d;
            $rentalTotalMinor = $lineTotal;
            $lines[] = [
                'type' => 'rental',
                'label' => (string) ($tariff['label'] ?? 'Аренда'),
                'unit_amount_minor' => $amountMinor,
                'quantity' => $d,
                'line_total_minor' => $lineTotal,
            ];
        } elseif ($kind === TariffKind::FixedPerRental) {
            $rentalTotalMinor = $amountMinor;
            $lines[] = [
                'type' => 'rental',
                'label' => (string) ($tariff['label'] ?? 'Аренда'),
                'unit_amount_minor' => $amountMinor,
                'quantity' => 1,
                'line_total_minor' => $amountMinor,
            ];
        } elseif ($kind === TariffKind::FixedPerHourBlock) {
            $hours = (int) ($tariff['block_hours'] ?? 24);
            $qty = $d;
            $lineTotal = $amountMinor * $qty;
            $rentalTotalMinor = $lineTotal;
            $lines[] = [
                'type' => 'rental',
                'label' => (string) ($tariff['label'] ?? 'Аренда'),
                'unit_amount_minor' => $amountMinor,
                'quantity' => $qty,
                'line_total_minor' => $lineTotal,
                'meta' => ['block_hours' => $hours],
            ];
        } else {
            return [
                'status' => 'invalid_profile',
                'error_code' => 'UNSUPPORTED_KIND',
                'message' => 'Тип тарифа не поддерживается для авторасчёта.',
            ];
        }

        $fin = is_array($profile['financial_terms'] ?? null) ? $profile['financial_terms'] : [];
        $depositMinor = isset($fin['deposit_amount_minor']) && $fin['deposit_amount_minor'] !== null
            ? (int) $fin['deposit_amount_minor']
            : null;
        $prepayMinor = isset($fin['prepayment_amount_minor']) && $fin['prepayment_amount_minor'] !== null
            ? (int) $fin['prepayment_amount_minor']
            : null;

        return [
            'status' => 'ok',
            'currency' => $currency,
            'selected_tariff' => [
                'id' => (string) ($tariff['id'] ?? ''),
                'label' => (string) ($tariff['label'] ?? ''),
                'kind' => (string) ($tariff['kind'] ?? ''),
            ],
            'duration' => [
                'days' => $d,
                'hours' => $d * 24,
            ],
            'lines' => $lines,
            'financial_terms' => [
                'deposit_amount_minor' => $depositMinor,
                'prepayment_amount_minor' => $prepayMinor,
            ],
            'totals' => [
                'rental_total_minor' => $rentalTotalMinor,
                'payable_now_minor' => null,
                'grand_total_minor' => $rentalTotalMinor,
            ],
            'presentation' => [
                'summary_text' => '',
                'note' => '',
            ],
            'warnings' => $v['warnings'],
            'profile_validity' => $v['validity']->value,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tariffs
     * @return ?array<string, mixed>
     */
    private function firstOnRequestTariff(array $tariffs): ?array
    {
        foreach ($tariffs as $t) {
            if (! is_array($t)) {
                continue;
            }
            $vis = is_array($t['visibility'] ?? null) ? $t['visibility'] : [];
            if (! ($vis['show_in_quote'] ?? true)) {
                continue;
            }
            if (($t['kind'] ?? '') === TariffKind::OnRequest->value) {
                return $t;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function onRequestPayload(array $tariff, string $currency, array $warnings): array
    {
        return [
            'status' => 'on_request',
            'currency' => $currency,
            'selected_tariff' => [
                'id' => (string) ($tariff['id'] ?? ''),
                'label' => (string) ($tariff['label'] ?? ''),
                'kind' => TariffKind::OnRequest->value,
            ],
            'presentation' => [
                'summary_text' => 'Цена рассчитывается по запросу',
                'note' => (string) ($tariff['note'] ?? 'Оставьте заявку, менеджер уточнит условия'),
            ],
            'warnings' => $warnings,
        ];
    }
}
