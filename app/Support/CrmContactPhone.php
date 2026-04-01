<?php

namespace App\Support;

/**
 * Отображение и tel:-ссылка для телефона в карточке CRM (тенант / платформа).
 */
final class CrmContactPhone
{
    public static function display(?string $raw): string
    {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return '';
        }

        $ru = RussianPhone::normalize($raw);
        if ($ru !== null) {
            return RussianPhone::toMasked($ru);
        }

        $max = 36;
        if (mb_strlen($raw) > $max) {
            return mb_substr($raw, 0, $max - 1).'…';
        }

        return $raw;
    }

    /**
     * Значение для href="tel:…" или null, если номер непригоден для набора.
     */
    public static function telHref(?string $raw): ?string
    {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }

        $ru = RussianPhone::normalize($raw);
        if ($ru !== null) {
            return $ru;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return '+'.$digits;
    }
}
