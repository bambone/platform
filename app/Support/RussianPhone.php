<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

/** Russian numbers: normalize to +7 + 10 digits; validate flexible input (mask, spaces, leading 8). */
final class RussianPhone
{
    /** +7XXXXXXXXXX or null if empty / cannot normalize. */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '7'.$digits;
        }

        if (strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            return null;
        }

        return '+'.$digits;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /**
     * Pattern for Filament {@see TextInput::tel()} via {@see TextInput::telRegex()}.
     *
     * Default Filament tel regex only allows one optional parenthesis group at the start, so masked RU numbers
     * like "+7 (913) 060-86-89" fail. This allows common display formatting; empty string is allowed
     * (field is usually nullable); non-empty values must contain at least one digit.
     */
    public static function filamentTelDisplayRegex(): string
    {
        return '/^$|^[\+\d\s\(\)\-\.\/]*\d[\+\d\s\(\)\-\.\/]*$/u';
    }

    /**
     * Format stored +7XXXXXXXXXX for the +7 (XXX) XXX-XX-XX mask field.
     */
    public static function toMasked(?string $stored): string
    {
        if ($stored === null || trim($stored) === '') {
            return '';
        }

        $n = self::normalize($stored);
        if ($n === null) {
            return trim($stored);
        }

        $d = substr($n, 2);

        return sprintf('+7 (%s) %s-%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 2), substr($d, 8, 2));
    }
}
