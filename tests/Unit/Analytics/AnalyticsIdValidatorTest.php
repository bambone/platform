<?php

namespace Tests\Unit\Analytics;

use App\Support\Analytics\AnalyticsIdValidator;
use PHPUnit\Framework\TestCase;

class AnalyticsIdValidatorTest extends TestCase
{
    public function test_yandex_accepts_digits_in_range(): void
    {
        $this->assertTrue(AnalyticsIdValidator::isValidYandexCounterId('12345'));
        $this->assertTrue(AnalyticsIdValidator::isValidYandexCounterId('123456789012345'));
    }

    public function test_yandex_rejects_short_or_non_digits(): void
    {
        $this->assertFalse(AnalyticsIdValidator::isValidYandexCounterId(''));
        $this->assertFalse(AnalyticsIdValidator::isValidYandexCounterId('1234'));
        $this->assertFalse(AnalyticsIdValidator::isValidYandexCounterId('1234567890123456'));
        $this->assertFalse(AnalyticsIdValidator::isValidYandexCounterId('12a45'));
        $this->assertFalse(AnalyticsIdValidator::isValidYandexCounterId('<script>'));
    }

    public function test_ga4_accepts_normalized_id(): void
    {
        $this->assertTrue(AnalyticsIdValidator::isValidGa4MeasurementId('G-ABC123DEF4'));
    }

    public function test_ga4_rejects_snippets_and_ua(): void
    {
        $this->assertFalse(AnalyticsIdValidator::isValidGa4MeasurementId("gtag('config','G-XXXX')"));
        $this->assertFalse(AnalyticsIdValidator::isValidGa4MeasurementId('UA-123456-1'));
        $this->assertFalse(AnalyticsIdValidator::isValidGa4MeasurementId('https://www.googletagmanager.com/gtag/js?id=G-XX'));
        $this->assertFalse(AnalyticsIdValidator::isValidGa4MeasurementId('G-'));
    }
}
