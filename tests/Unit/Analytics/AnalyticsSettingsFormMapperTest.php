<?php

namespace Tests\Unit\Analytics;

use App\Support\Analytics\AnalyticsSettingsFormMapper;
use App\Support\Analytics\AnalyticsValidationMessages;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsSettingsFormMapperTest extends TestCase
{
    #[Test]
    public function enabled_yandex_without_id_throws(): void
    {
        try {
            AnalyticsSettingsFormMapper::toValidatedData([
                'analytics_yandex_metrica_enabled' => true,
                'analytics_yandex_counter_id' => '',
                'analytics_ga4_enabled' => false,
                'analytics_ga4_measurement_id' => '',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                AnalyticsValidationMessages::YANDEX_COUNTER,
                $e->errors()['data.analytics_yandex_counter_id'][0] ?? null
            );
        }
    }

    #[Test]
    public function enabled_ga4_without_id_throws(): void
    {
        try {
            AnalyticsSettingsFormMapper::toValidatedData([
                'analytics_yandex_metrica_enabled' => false,
                'analytics_yandex_counter_id' => '',
                'analytics_ga4_enabled' => true,
                'analytics_ga4_measurement_id' => '',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                AnalyticsValidationMessages::GA4_MEASUREMENT,
                $e->errors()['data.analytics_ga4_measurement_id'][0] ?? null
            );
        }
    }

    #[Test]
    public function disabled_yandex_clears_counter_and_flags(): void
    {
        $dto = AnalyticsSettingsFormMapper::toValidatedData([
            'analytics_yandex_metrica_enabled' => false,
            'analytics_yandex_counter_id' => '  12345  ',
            'analytics_yandex_webvisor_enabled' => true,
            'analytics_yandex_clickmap_enabled' => true,
            'analytics_yandex_track_links_enabled' => true,
            'analytics_yandex_accurate_bounce_enabled' => true,
            'analytics_ga4_enabled' => false,
            'analytics_ga4_measurement_id' => '',
        ]);

        $this->assertFalse($dto->yandexEnabled);
        $this->assertSame('', $dto->yandexCounterId);
        $this->assertFalse($dto->yandexWebvisor);
    }

    #[Test]
    public function ga4_measurement_normalized_to_uppercase(): void
    {
        $dto = AnalyticsSettingsFormMapper::toValidatedData([
            'analytics_yandex_metrica_enabled' => false,
            'analytics_yandex_counter_id' => '',
            'analytics_ga4_enabled' => true,
            'analytics_ga4_measurement_id' => 'g-ab12cd34',
        ]);

        $this->assertSame('G-AB12CD34', $dto->ga4MeasurementId);
    }

    #[Test]
    public function rejects_gtag_snippet_whole_string(): void
    {
        try {
            AnalyticsSettingsFormMapper::toValidatedData([
                'analytics_yandex_metrica_enabled' => false,
                'analytics_yandex_counter_id' => '',
                'analytics_ga4_enabled' => true,
                'analytics_ga4_measurement_id' => "gtag('config','G-ABCDEF123')",
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
