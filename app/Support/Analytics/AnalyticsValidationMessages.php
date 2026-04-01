<?php

namespace App\Support\Analytics;

final class AnalyticsValidationMessages
{
    public const YANDEX_COUNTER = 'Укажите только числовой ID счётчика, без HTML/JS и без полного кода вставки.';

    public const GA4_MEASUREMENT = 'Укажите только Measurement ID формата G-XXXXXXXXXX, без gtag-кода и без HTML.';
}
