<?php

namespace App\Services\Analytics;

use App\Models\TenantDomain;
use App\Support\Analytics\AnalyticsSettingsData;
use App\Support\Analytics\ResolvedPublicAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

final class AnalyticsSnippetRenderer
{
    private const string RESOLVED_PUBLIC_ANALYTICS_ATTRIBUTE = '_analytics_resolved_public';

    public function __construct(
        private readonly AnalyticsSettingsPersistence $persistence,
        private readonly PlatformMarketingAnalyticsPersistence $platformMarketingPersistence,
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
        $request ??= request();

        try {
            return $this->renderHeadHtmlInternal($request);
        } catch (\Throwable $e) {
            $this->logRenderFailure($e, $request);

            return '';
        }
    }

    /**
     * Yandex Metrika noscript (body): must match {@see renderHeadHtml} via {@see resolvePublicAnalytics}.
     */
    public function renderYandexNoscriptBodyHtml(?Request $request = null): string
    {
        $request ??= request();

        try {
            return $this->renderYandexNoscriptBodyInternal($request);
        } catch (\Throwable $e) {
            $this->logRenderFailure($e, $request);

            return '';
        }
    }

    public function resolvePublicAnalytics(?Request $request = null): ResolvedPublicAnalytics
    {
        $request ??= request();
        if ($request->attributes->has(self::RESOLVED_PUBLIC_ANALYTICS_ATTRIBUTE)) {
            return $request->attributes->get(self::RESOLVED_PUBLIC_ANALYTICS_ATTRIBUTE);
        }

        $resolved = $this->computeResolvedPublicAnalytics($request);
        $request->attributes->set(self::RESOLVED_PUBLIC_ANALYTICS_ATTRIBUTE, $resolved);

        return $resolved;
    }

    private function renderHeadHtmlInternal(Request $request): string
    {
        if (! $this->shouldRenderForRequest($request)) {
            return '';
        }

        $resolved = $this->resolvePublicAnalytics($request);
        if (! $resolved->shouldRenderGa4() && ! $resolved->shouldRenderYandex()) {
            return '';
        }

        return $this->buildHeadSnippetsHtml($resolved);
    }

    private function renderYandexNoscriptBodyInternal(Request $request): string
    {
        if (! $this->shouldRenderForRequest($request)) {
            return '';
        }

        $resolved = $this->resolvePublicAnalytics($request);
        if (! $resolved->shouldRenderYandex()) {
            return '';
        }

        return View::make('analytics.yandex-metrica-noscript', [
            'counterId' => $resolved->yandexCounterId,
        ])->render();
    }

    private function computeResolvedPublicAnalytics(Request $request): ResolvedPublicAnalytics
    {
        if (! $this->shouldRenderForRequest($request)) {
            return ResolvedPublicAnalytics::empty();
        }

        $data = $this->resolveSettingsData($request);
        if ($data === null || ! $this->hasRenderableProviders($data)) {
            return ResolvedPublicAnalytics::empty();
        }

        $ymEnabled = (bool) config('analytics.providers.yandex_metrica.enabled', true);
        $gaEnabled = (bool) config('analytics.providers.ga4.enabled', true);

        $yandexCounterId = null;
        if ($ymEnabled && $data->hasRenderableYandex()) {
            $yandexCounterId = (int) $data->yandexCounterId;
        }

        $ga4MeasurementId = null;
        if ($gaEnabled && $data->hasRenderableGa4()) {
            $ga4MeasurementId = $data->ga4MeasurementId;
        }

        return new ResolvedPublicAnalytics(
            yandexCounterId: $yandexCounterId,
            yandexClickmap: $data->yandexClickmap,
            yandexTrackLinks: $data->yandexTrackLinks,
            yandexAccurateTrackBounce: $data->yandexAccurateBounce,
            yandexWebvisor: $data->yandexWebvisor,
            yandexIncludeSsr: (bool) config('analytics.providers.yandex_metrica.ssr', false),
            yandexIncludeEcommerceDataLayer: (bool) config('analytics.providers.yandex_metrica.ecommerce_data_layer', false),
            ga4MeasurementId: $ga4MeasurementId,
        );
    }

    private function resolveSettingsData(Request $request): ?AnalyticsSettingsData
    {
        $tenant = currentTenant();
        if ($tenant !== null) {
            return $this->persistence->load((int) $tenant->id);
        }

        if ($this->requestIsPlatformMarketingPublicContext($request)) {
            return $this->platformMarketingPersistence->load();
        }

        return null;
    }

    /**
     * Настройки маркетингового счётчика: домены из TENANCY_CENTRAL_DOMAINS или любой запрос к именованным маршрутам platform.*
     * (иначе при расхождении www/apex и env счётчик не попадал в HTML и проверка ?_ym_status-check не срабатывала).
     */
    private function requestIsPlatformMarketingPublicContext(Request $request): bool
    {
        $host = TenantDomain::normalizeHost($request->getHost());
        foreach (config('tenancy.central_domains', []) as $h) {
            if ($host === TenantDomain::normalizeHost((string) $h)) {
                return true;
            }
        }

        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'platform.');
    }

    private function buildHeadSnippetsHtml(ResolvedPublicAnalytics $resolved): string
    {
        $parts = [];

        if ($resolved->shouldRenderGa4()) {
            $parts[] = View::make('analytics.ga4', [
                'measurementId' => $resolved->ga4MeasurementId,
            ])->render();
        }

        if ($resolved->shouldRenderYandex()) {
            $parts[] = View::make('analytics.yandex-metrica-head', [
                'counterId' => $resolved->yandexCounterId,
                'clickmap' => $resolved->yandexClickmap,
                'trackLinks' => $resolved->yandexTrackLinks,
                'accurateTrackBounce' => $resolved->yandexAccurateTrackBounce,
                'webvisor' => $resolved->yandexWebvisor,
                'includeSsr' => $resolved->yandexIncludeSsr,
                'includeEcommerceDataLayer' => $resolved->yandexIncludeEcommerceDataLayer,
            ])->render();
        }

        return implode("\n", array_filter($parts));
    }

    private function logRenderFailure(\Throwable $e, Request $request): void
    {
        Log::warning('analytics_snippet_render_failed', [
            'exception' => $e->getMessage(),
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
            'analytics_context' => $this->analyticsContextLabel($request),
        ]);
    }

    private function analyticsContextLabel(Request $request): string
    {
        if (currentTenant() !== null) {
            return 'tenant';
        }

        if ($this->requestIsPlatformMarketingPublicContext($request)) {
            return 'platform_marketing';
        }

        return 'none';
    }
}
