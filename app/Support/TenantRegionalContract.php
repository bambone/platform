<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Минимальная нормализация локали, валюты и страны для карточки клиента и онбординга.
 */
final class TenantRegionalContract
{
    public static function normalizeLocale(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = trim(str_replace('_', '-', $input));
        if ($s === '') {
            return null;
        }
        $parts = explode('-', $s);
        $parts[0] = strtolower($parts[0]);
        for ($i = 1, $n = count($parts); $i < $n; $i++) {
            $p = $parts[$i];
            if (strlen($p) === 2 && ctype_alpha($p)) {
                $parts[$i] = strtoupper($p);
            } else {
                $parts[$i] = strtolower($p);
            }
        }

        return implode('-', $parts);
    }

    /** ISO 4217-style uppercase 3-letter code. */
    public static function normalizeCurrency(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = strtoupper(trim($input));
        if ($s === '') {
            return null;
        }
        if (strlen($s) !== 3 || ! ctype_alpha($s)) {
            return $s;
        }

        return $s;
    }

    /** ISO 3166-1 alpha-2 uppercase. */
    public static function normalizeCountry(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = strtoupper(trim($input));
        if ($s === '') {
            return null;
        }
        if (strlen($s) !== 2 || ! ctype_alpha($s)) {
            return $s;
        }

        return $s;
    }

    /** Нормализованная локаль: язык 2–3 буквы + опциональные сегменты (BCP-47-подобно). */
    public static function isValidLocale(?string $normalized): bool
    {
        if ($normalized === null || $normalized === '') {
            return false;
        }

        return (bool) preg_match('/^[a-z]{2,3}(-[a-zA-Z0-9]{1,8})*$/', $normalized);
    }

    public static function isValidCurrency(?string $normalized): bool
    {
        if ($normalized === null || $normalized === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Z]{3}$/', $normalized);
    }

    /** Пусто допустимо (поле необязательное); иначе ровно две латинские буквы. */
    public static function isValidCountryOrEmpty(?string $normalized): bool
    {
        if ($normalized === null || $normalized === '') {
            return true;
        }

        return (bool) preg_match('/^[A-Z]{2}$/', $normalized);
    }
}
