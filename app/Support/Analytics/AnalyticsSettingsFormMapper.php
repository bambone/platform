<?php

namespace App\Support\Analytics;

use Illuminate\Validation\ValidationException;

/**
 * Maps Filament form state to AnalyticsSettingsData with normalization, disabled-provider contract, and validation.
 */
final class AnalyticsSettingsFormMapper
{
    /**
     * @param  array<string, mixed>  $form
     */
    public static function toValidatedData(array $form): AnalyticsSettingsData
    {
        $yandexEnabled = (bool) ($form['analytics_yandex_metrica_enabled'] ?? false);
        $ga4Enabled = (bool) ($form['analytics_ga4_enabled'] ?? false);

        $counterRaw = AnalyticsInputNormalizer::normalizeOptionalString(
            is_string($form['analytics_yandex_counter_id'] ?? null)
                ? $form['analytics_yandex_counter_id']
                : (string) ($form['analytics_yandex_counter_id'] ?? '')
        );

        $measurementRaw = AnalyticsInputNormalizer::normalizeGa4MeasurementId(
            is_string($form['analytics_ga4_measurement_id'] ?? null)
                ? $form['analytics_ga4_measurement_id']
                : (string) ($form['analytics_ga4_measurement_id'] ?? '')
        );

        if (! $yandexEnabled) {
            $counterRaw = '';
            $yandexWebvisor = false;
            $yandexClickmap = false;
            $yandexTrackLinks = false;
            $yandexAccurateBounce = false;
        } else {
            $yandexWebvisor = (bool) ($form['analytics_yandex_webvisor_enabled'] ?? false);
            $yandexClickmap = (bool) ($form['analytics_yandex_clickmap_enabled'] ?? false);
            $yandexTrackLinks = (bool) ($form['analytics_yandex_track_links_enabled'] ?? false);
            $yandexAccurateBounce = (bool) ($form['analytics_yandex_accurate_bounce_enabled'] ?? false);
        }

        if (! $ga4Enabled) {
            $measurementRaw = '';
        }

        if ($yandexEnabled) {
            if ($counterRaw === '' || ! AnalyticsIdValidator::isValidYandexCounterId($counterRaw)) {
                throw ValidationException::withMessages([
                    'data.analytics_yandex_counter_id' => AnalyticsValidationMessages::YANDEX_COUNTER,
                ]);
            }
        }

        if ($ga4Enabled) {
            if ($measurementRaw === '' || ! AnalyticsIdValidator::isValidGa4MeasurementId($measurementRaw)) {
                throw ValidationException::withMessages([
                    'data.analytics_ga4_measurement_id' => AnalyticsValidationMessages::GA4_MEASUREMENT,
                ]);
            }
        }

        return new AnalyticsSettingsData(
            yandexEnabled: $yandexEnabled,
            yandexCounterId: $yandexEnabled ? $counterRaw : '',
            yandexWebvisor: $yandexWebvisor,
            yandexClickmap: $yandexClickmap,
            yandexTrackLinks: $yandexTrackLinks,
            yandexAccurateBounce: $yandexAccurateBounce,
            ga4Enabled: $ga4Enabled,
            ga4MeasurementId: $ga4Enabled ? $measurementRaw : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function toFormState(AnalyticsSettingsData $data): array
    {
        return [
            'analytics_yandex_metrica_enabled' => $data->yandexEnabled,
            'analytics_yandex_counter_id' => $data->yandexCounterId,
            'analytics_yandex_webvisor_enabled' => $data->yandexWebvisor,
            'analytics_yandex_clickmap_enabled' => $data->yandexClickmap,
            'analytics_yandex_track_links_enabled' => $data->yandexTrackLinks,
            'analytics_yandex_accurate_bounce_enabled' => $data->yandexAccurateBounce,
            'analytics_ga4_enabled' => $data->ga4Enabled,
            'analytics_ga4_measurement_id' => $data->ga4MeasurementId,
        ];
    }
}
