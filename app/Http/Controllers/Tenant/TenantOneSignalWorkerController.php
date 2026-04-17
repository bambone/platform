<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Thin OneSignal service worker; separate scope from site PWA SW.
 *
 * @see https://documentation.onesignal.com/docs/en/onesignal-service-worker
 */
class TenantOneSignalWorkerController extends Controller
{
    public function sdkWorker(): Response
    {
        $js = <<<'JS'
importScripts('https://cdn.onesignal.com/sdks/OneSignalSDKWorker.js');

JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function sdkUpdaterWorker(): Response
    {
        $js = <<<'JS'
importScripts('https://cdn.onesignal.com/sdks/OneSignalSDKWorker.js');

JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
