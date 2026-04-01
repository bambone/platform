<?php

namespace App\Support\Analytics;

final class AnalyticsIdValidator
{
    private const YANDEX_MIN_LEN = 5;

    private const YANDEX_MAX_LEN = 15;

    private const GA4_MAX_LEN = 32;

    /**
     * @param  string  $normalized  Already normalized counter id (trimmed digits-only or empty).
     */
    public static function isValidYandexCounterId(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        $len = strlen($normalized);

        if ($len < self::YANDEX_MIN_LEN || $len > self::YANDEX_MAX_LEN) {
            return false;
        }

        return (bool) preg_match('/^\d+$/', $normalized);
    }

    /**
     * @param  string  $normalized  Uppercased G-XXXXXXXX form or empty.
     */
    public static function isValidGa4MeasurementId(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        if (strlen($normalized) > self::GA4_MAX_LEN) {
            return false;
        }

        return (bool) preg_match('/^G-[A-Z0-9]+$/', $normalized);
    }
}
