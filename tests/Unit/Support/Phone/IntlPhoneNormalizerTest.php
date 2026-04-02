<?php

namespace Tests\Unit\Support\Phone;

use App\Support\Phone\IntlPhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntlPhoneNormalizerTest extends TestCase
{
    public function test_sanitize_strips_tel_prefix(): void
    {
        $this->assertSame('+7 915', IntlPhoneNormalizer::sanitizePhoneInput('tel:+7 915 '));
    }

    #[DataProvider('normalizeRuProvider')]
    public function test_normalize_russia_variants(string $raw, string $expected): void
    {
        $this->assertSame($expected, IntlPhoneNormalizer::normalizePhone($raw));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function normalizeRuProvider(): iterable
    {
        yield '8 full' => ['89151784589', '+79151784589'];
        yield '7 full' => ['79151784589', '+79151784589'];
        yield '10 national' => ['9151784589', '+79151784589'];
        yield 'plus 7' => ['+79151784589', '+79151784589'];
        yield 'formatted paste' => ['8 (915) 178-45-89', '+79151784589'];
        yield 'plus spaced' => ['+7 915 178 45 89', '+79151784589'];
        yield 'tel uri' => ['tel:+7(915)1784589', '+79151784589'];
        yield 'junk around plus' => ['мой номер +7 915 178 45 89', '+79151784589'];
        yield 'masked parentheses hyphen booking modal' => ['+7 (951) 784-58-89', '+79517845889'];
    }

    #[DataProvider('normalizeInternationalProvider')]
    public function test_normalize_international(string $raw, string $expected): void
    {
        $this->assertSame($expected, IntlPhoneNormalizer::normalizePhone($raw));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function normalizeInternationalProvider(): iterable
    {
        yield 'nanp' => ['+14155552671', '+14155552671'];
        yield 'gb' => ['+44 7700 900123', '+447700900123'];
        yield 'de' => ['+49 1512 3456789', '+4915123456789'];
        yield 'am' => ['+374 91 234567', '+37491234567'];
        yield 'ae' => ['+971501234567', '+971501234567'];
        yield '00 germany' => ['0049 1512 3456789', '+4915123456789'];
        yield '11 digit nanp no plus' => ['14155552671', '+14155552671'];
    }

    public function test_normalize_unknown_country_still_e164_like(): void
    {
        $this->assertSame('+359888123456', IntlPhoneNormalizer::normalizePhone('+359 888 123 456'));
    }

    public function test_normalize_empty(): void
    {
        $this->assertSame('', IntlPhoneNormalizer::normalizePhone(''));
        $this->assertSame('', IntlPhoneNormalizer::normalizePhone(null));
    }

    public function test_normalize_plus_only_returns_plus(): void
    {
        $this->assertSame('+', IntlPhoneNormalizer::normalizePhone('+'));
    }

    #[DataProvider('validateProvider')]
    public function test_validate_phone(string $normalized, bool $ok): void
    {
        $this->assertSame($ok, IntlPhoneNormalizer::validatePhone($normalized));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function validateProvider(): iterable
    {
        yield 'ru ok' => ['+79151784589', true];
        yield 'ru short' => ['+79151', false];
        yield 'nanp ok' => ['+14155552671', true];
        yield 'nanp short' => ['+1415555', false];
        yield 'gb ok' => ['+447700900123', true];
        yield 'de ok 10 nat' => ['+491512345678', true];
        yield 'de ok 11 nat' => ['+4915123456789', true];
        yield 'am ok' => ['+37491234567', true];
        yield 'ae ok' => ['+971501234567', true];
        yield 'fallback min length' => ['+35988812', true];
        yield 'fallback too short' => ['+359881', false];
        yield 'empty' => ['', false];
        yield 'plus only' => ['+', false];
        yield 'leading zero cc invalid e164' => ['+0799123456', false];
    }

    public function test_detect_country_prefers_longer_codes(): void
    {
        $row = IntlPhoneNormalizer::detectCountryByDigits('37491234567');
        $this->assertNotNull($row);
        $this->assertSame('374', $row['code']);
    }

    /** Регрессия: маска как в UI модалки бронирования должна проходить после normalize (как в StoreLeadRequest). */
    public function test_formatted_russian_phone_from_booking_ui_normalizes_and_validates(): void
    {
        $formatted = '+7 (951) 784-58-89';
        $normalized = IntlPhoneNormalizer::normalizePhone($formatted);
        $this->assertSame('+79517845889', $normalized);
        $this->assertTrue(IntlPhoneNormalizer::validatePhone($normalized));
    }
}
