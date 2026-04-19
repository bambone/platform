<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Идентификатор для User-Agent Nominatim (политика OSMF: валидный контакт приложения).
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
final class NominatimContactIdentifier
{
    /**
     * Адрес в скобках User-Agent. Публичный Nominatim часто отвечает 403 на очевидные
     * placeholder'ы вроде *@example.com из дефолтного MAIL_FROM_ADDRESS.
     */
    public static function resolveForUserAgent(): string
    {
        return self::resolve(
            env('NOMINATIM_CONTACT'),
            env('MAIL_FROM_ADDRESS'),
            env('APP_URL'),
        );
    }

    public static function resolve(mixed $explicitFromEnv, mixed $mailFromEnv, mixed $appUrlEnv): string
    {
        if (is_string($explicitFromEnv) && trim($explicitFromEnv) !== '') {
            return trim($explicitFromEnv);
        }

        $mail = trim(self::stringifyEnv($mailFromEnv));
        if ($mail !== '' && ! self::isDisallowedMailDomain($mail)) {
            return $mail;
        }

        $url = self::stringifyEnv($appUrlEnv);
        $url = $url !== '' ? $url : 'http://localhost';

        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : 'localhost';

        return 'geocoding@'.$host;
    }

    private static function stringifyEnv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    private static function isDisallowedMailDomain(string $email): bool
    {
        if (! str_contains($email, '@')) {
            return true;
        }

        $domain = strtolower(substr($email, (int) strrpos($email, '@') + 1));

        return in_array($domain, ['example.com', 'example.org', 'example.net'], true);
    }
}
