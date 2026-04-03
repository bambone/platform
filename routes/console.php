<?php

use App\Jobs\RecalculateAllTenantStorageUsageJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tenants:refresh-seo-sitemaps --stale-only')
    ->dailyAt('03:15')
    ->withoutOverlapping();

Schedule::job(new RecalculateAllTenantStorageUsageJob(false))
    ->dailyAt('03:50')
    ->withoutOverlapping();
