<?php

return [

    'default_base_quota_bytes' => (int) env('TENANT_STORAGE_DEFAULT_BASE_QUOTA_BYTES', 100 * 1024 * 1024),

    'default_warning_threshold_percent' => (int) env('TENANT_STORAGE_WARNING_THRESHOLD_PERCENT', 20),

    'default_critical_threshold_percent' => (int) env('TENANT_STORAGE_CRITICAL_THRESHOLD_PERCENT', 10),

    'default_hard_stop_enabled' => filter_var(env('TENANT_STORAGE_HARD_STOP_DEFAULT', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | After this many hours without a successful storage sync, tenant UI may show a stale hint.
    */
    'stale_sync_hours' => (int) env('TENANT_STORAGE_STALE_SYNC_HOURS', 72),

];
