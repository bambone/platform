<?php

namespace App\Services\Analytics;

use App\Support\Analytics\AnalyticsSettingsData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class AnalyticsSnippetRenderer
{
    public function __construct(
        private readonly AnalyticsSettingsPersistence $persistence,
    ) {}

    public function shouldRenderForRequest(?Request $request = null): bool
    {
        $request ??= request();

        // PHPUnit reports runningInConsole()=true; allow feature tests that perform HTTP when env allows.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        if (is_string($routeName) && str_starts_with($routeName, 'filament.')) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return false;
        }
        if ($path === 'platform' || str_starts_with($path, 'platform/')) {
            return false;
        }

        if (config('analytics.force_render')) {
            return true;
        }

        if (app()->environment('local') && ! config('analytics.render_in_local', false)) {
            return false;
        }

        if (app()->environment('testing') && ! config('analytics.render_in_testing', false)) {
            return false;
        }

        if (app()->environment('staging') && ! config('analytics.render_in_staging', false)) {
            return false;
        }

        return true;
    }

    public function hasRenderableProviders(AnalyticsSettingsData $data): bool
    {
        $ym = (bool) config('analytics.providers.yandex_metrica.enabled', true);
        $ga = (bool) config('analytics.providers.ga4.enabled', true);

        if ($ym && $data->hasRenderableYandex()) {
            return true;
        }

        if ($ga && $data->hasRenderableGa4()) {
            return true;
        }

        return false;
    }

    /**
     * Safe HTML fragments from platform-controlled Blade only. Empty string if nothing to render.
     */
    public function renderHeadHtml(?Request $request = null): string
    {
        try {
            return $this->renderHeadHtmlInternal($request);
        } catch (\Throwable) {
            return '';
        }
    }

    private function renderHeadHtmlInternal(?Request $request = null): string
    {
        $request ??= request();

        if (! $this->shouldRenderForRequest($request)) {
            return '';
        }

        $tenant = currentTenant();
        if ($tenant === null) {
            return '';
        }

        $data = $this->persistence->load((int) $tenant->id);

        if (! $this->hasRenderableProviders($data)) {
            return '';
        }

        $parts = [];

        if (config('analytics.providers.ga4.enabled', true) && $data->hasRenderableGa4()) {
            $parts[] = View::make('analytics.ga4', [
                'measurementId' => $data->ga4MeasurementId,
            ])->render();
        }

        if (config('analytics.providers.yandex_metrica.enabled', true) && $data->hasRenderableYandex()) {
            $parts[] = View::make('analytics.yandex-metrica', [
                'counterId' => (int) $data->yandexCounterId,
                'webvisor' => $data->yandexWebvisor,
                'clickmap' => $data->yandexClickmap,
                'trackLinks' => $data->yandexTrackLinks,
                'accurateTrackBounce' => $data->yandexAccurateBounce,
            ])->render();
        }

        return implode("\n", array_filter($parts));

    }
}
