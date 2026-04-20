<?php

declare(strict_types=1);

namespace App\MotorcyclePricing;

/**
 * Picks a single auto-quotable tariff for a duration.
 *
 * Policy: collect candidates (kinds that can carry a price, applicability matches days, not manual-only / not on-request).
 * Sort by specificity (narrow duration range beats “always”, then min-days bands, then narrower windows, then lower `priority`, then stable id).
 * In the admin pricing profile, `priority` is derived from repeater row order after save (first row = lowest priority number = wins ties).
 * If the top two candidates share the same tie-breaker tuple, return conflict → {@see MotorcycleQuoteEngine} surfaces invalid_profile.
 */
final class MotorcycleTariffResolver
{
    /**
     * @param  list<array<string, mixed>>  $tariffs
     * @return array{tariff: ?array<string, mixed>, conflict: bool, reason: ?string}
     */
    public function resolveForAutoQuote(array $tariffs, int $days): array
    {
        $candidates = [];
        foreach ($tariffs as $t) {
            if (! is_array($t)) {
                continue;
            }
            $vis = is_array($t['visibility'] ?? null) ? $t['visibility'] : [];
            if (! ($vis['show_in_quote'] ?? true)) {
                continue;
            }
            $kind = TariffKind::tryFrom((string) ($t['kind'] ?? ''));
            if ($kind === null || $kind === TariffKind::Informational) {
                continue;
            }
            $app = is_array($t['applicability'] ?? null) ? $t['applicability'] : [];
            $mode = ApplicabilityMode::tryFrom((string) ($app['mode'] ?? ''));
            if ($mode === ApplicabilityMode::ManualOnly) {
                continue;
            }
            if ($kind === TariffKind::OnRequest) {
                continue;
            }
            if (! $this->applicabilityMatches($mode, $app, $days)) {
                continue;
            }
            $candidates[] = $t;
        }

        if ($candidates === []) {
            return ['tariff' => null, 'conflict' => false, 'reason' => 'no_matching_tariff'];
        }

        usort($candidates, function (array $a, array $b) use ($days): int {
            $ka = $this->sortKey($a, $days);
            $kb = $this->sortKey($b, $days);
            for ($i = 0; $i <= 3; $i++) {
                if ($ka[$i] !== $kb[$i]) {
                    return $ka[$i] <=> $kb[$i];
                }
            }

            return strcmp($ka[4], $kb[4]);
        });

        $first = $candidates[0];
        $second = $candidates[1] ?? null;
        if ($second !== null) {
            $t0 = $this->tieFingerprint($first, $days);
            $t1 = $this->tieFingerprint($second, $days);
            if ($t0 === $t1) {
                return ['tariff' => null, 'conflict' => true, 'reason' => 'unresolved_tariff_tie'];
            }
        }

        return ['tariff' => $first, 'conflict' => false, 'reason' => null];
    }

    /**
     * @param  array<string, mixed>  $app
     */
    private function applicabilityMatches(?ApplicabilityMode $mode, array $app, int $days): bool
    {
        if ($mode === null || $mode === ApplicabilityMode::Always) {
            return true;
        }
        if ($mode === ApplicabilityMode::DurationRangeDays) {
            $min = (int) ($app['min_days'] ?? 1);
            $max = (int) ($app['max_days'] ?? $min);

            return $days >= $min && $days <= $max;
        }
        if ($mode === ApplicabilityMode::DurationMinDays) {
            $min = (int) ($app['min_days'] ?? 1);

            return $days >= $min;
        }

        return false;
    }

    /**
     * Lower tuple wins (first element most significant).
     *
     * @return array{int, int, int, int, string}
     */
    private function sortKey(array $tariff, int $days): array
    {
        $app = is_array($tariff['applicability'] ?? null) ? $tariff['applicability'] : [];
        $mode = ApplicabilityMode::tryFrom((string) ($app['mode'] ?? ''));

        $specificityBand = 2;
        $narrowness = 0;
        $minDaysKey = 0;

        if ($mode === ApplicabilityMode::Always) {
            $specificityBand = 2;
            $narrowness = 1_000_000;
        } elseif ($mode === ApplicabilityMode::DurationRangeDays) {
            $specificityBand = 0;
            $min = (int) ($app['min_days'] ?? 1);
            $max = (int) ($app['max_days'] ?? $min);
            $narrowness = max(0, $max - $min);
        } elseif ($mode === ApplicabilityMode::DurationMinDays) {
            $specificityBand = 1;
            $minDaysKey = 10_000 - (int) ($app['min_days'] ?? 1) * 100;
        }

        $priority = (int) ($tariff['priority'] ?? 500);

        return [$specificityBand, $narrowness, $minDaysKey, $priority, (string) ($tariff['id'] ?? '')];
    }

    /**
     * @return array{int, int, int, int}
     */
    private function tieFingerprint(array $tariff, int $days): array
    {
        $k = $this->sortKey($tariff, $days);

        return [(int) $k[0], (int) $k[1], (int) $k[2], (int) $k[3]];
    }
}
