<?php

namespace App\Support\Analytics;

/**
 * Safe normalization only: trim and case. Does not extract IDs from snippets.
 */
final class AnalyticsInputNormalizer
{
    /**
     * Trim and collapse leading/trailing whitespace (full string still validated as a whole).
     */
    public static function normalizeOptionalString(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(preg_replace('/^\s+|\s+$/u', '', $value) ?? '');
    }

    public static function normalizeGa4MeasurementId(string $value): string
    {
        $s = self::normalizeOptionalString($value);
        if ($s === '') {
            return '';
        }

        if (! str_starts_with($s, 'G-') && ! str_starts_with($s, 'g-')) {
            return $s;
        }

        return 'G-'.strtoupper(substr($s, 2));
    }
}
