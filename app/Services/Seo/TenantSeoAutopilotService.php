<?php

namespace App\Services\Seo;

use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Seo\Data\TenantSeoAutopilotResult;
use App\Services\Seo\Data\TenantSeoBootstrapData;

final class TenantSeoAutopilotService
{
    public function __construct(
        private TenantSeoBootstrapDataBuilder $bootstrapBuilder,
        private TenantSeoRouteOverridesBuilder $routeOverridesBuilder,
        private TenantLlmsTxtGenerator $llmsGenerator,
        private SeoRouteRegistry $registry,
    ) {}

    public function run(Tenant $tenant, bool $force = false, bool $dryRun = false): TenantSeoAutopilotResult
    {
        $data = $this->bootstrapBuilder->build($tenant);
        $messages = [];

        $wroteIntro = false;
        $wroteEntries = false;
        $wroteRoutes = false;
        $touchedHome = false;

        $introCurrent = trim((string) TenantSetting::getForTenant($tenant->id, 'seo.llms_intro', ''));
        $entriesCurrent = trim((string) TenantSetting::getForTenant($tenant->id, 'seo.llms_entries', ''));
        $routesCurrent = trim((string) TenantSetting::getForTenant($tenant->id, 'seo.route_overrides', ''));

        $llms = $this->llmsGenerator->generate($tenant, $data);

        if ($force || $introCurrent === '') {
            if (! $dryRun) {
                TenantSetting::setForTenant($tenant->id, 'seo.llms_intro', $llms['intro'], 'string');
            }
            $wroteIntro = true;
            $messages[] = 'seo.llms_intro '.($dryRun ? '(dry-run) ' : '').'set';
        }

        if ($force || $entriesCurrent === '') {
            $json = json_encode($llms['entries'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                if (! $dryRun) {
                    TenantSetting::setForTenant($tenant->id, 'seo.llms_entries', $json, 'string');
                }
                $wroteEntries = true;
                $messages[] = 'seo.llms_entries '.($dryRun ? '(dry-run) ' : '').'set';
            }
        }

        $builtRoutes = $this->routeOverridesBuilder->build($data);
        $mergedRoutes = $this->mergeRouteOverridesJson($routesCurrent, $builtRoutes, $force);
        if ($mergedRoutes !== null && ($force || $this->routeOverridesNeedWrite($routesCurrent, $mergedRoutes))) {
            $encoded = json_encode($mergedRoutes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                if (! $dryRun) {
                    TenantSetting::setForTenant($tenant->id, 'seo.route_overrides', $encoded, 'string');
                }
                $wroteRoutes = true;
                $messages[] = 'seo.route_overrides '.($dryRun ? '(dry-run) ' : '').'updated';
            }
        }

        if ($data->hasPublishedHomePage) {
            $touchedHome = $this->ensureHomeSeoMeta($tenant, $data, $dryRun, $messages, $force);
        }

        return new TenantSeoAutopilotResult(
            dryRun: $dryRun,
            wroteLlmsIntro: $wroteIntro,
            wroteLlmsEntries: $wroteEntries,
            wroteRouteOverrides: $wroteRoutes,
            touchedHomeSeoMeta: $touchedHome,
            messages: $messages,
        );
    }

    /**
     * Rewrites only `seo.llms_intro` and `seo.llms_entries` from current bootstrap data.
     */
    public function refreshLlmsOnly(Tenant $tenant, bool $dryRun = false): void
    {
        $data = $this->bootstrapBuilder->build($tenant);
        $llms = $this->llmsGenerator->generate($tenant, $data);
        if ($dryRun) {
            return;
        }
        TenantSetting::setForTenant($tenant->id, 'seo.llms_intro', $llms['intro'], 'string');
        $json = json_encode($llms['entries'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            TenantSetting::setForTenant($tenant->id, 'seo.llms_entries', $json, 'string');
        }
    }

    /**
     * @param  array<string, array<string, string>>  $built
     * @return array<string, array<string, string>>|null
     */
    private function mergeRouteOverridesJson(string $currentRaw, array $built, bool $force): ?array
    {
        $existing = [];
        if ($currentRaw !== '') {
            $decoded = json_decode($currentRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (is_string($k) && is_array($v)) {
                        $existing[$k] = $v;
                    }
                }
            }
        }

        if ($force) {
            foreach ($built as $routeName => $row) {
                $existing[$routeName] = $row;
            }

            return $existing;
        }

        foreach ($built as $routeName => $row) {
            if (! isset($existing[$routeName])) {
                $existing[$routeName] = $row;
            }
        }

        return $existing;
    }

    /**
     * @param  array<string, array<string, string>>  $merged
     */
    private function routeOverridesNeedWrite(string $currentRaw, array $merged): bool
    {
        if ($currentRaw === '') {
            return $merged !== [];
        }

        $decoded = json_decode($currentRaw, true);
        if (! is_array($decoded)) {
            return true;
        }

        foreach ($merged as $routeName => $row) {
            if (! isset($decoded[$routeName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $messages
     */
    private function ensureHomeSeoMeta(Tenant $tenant, TenantSeoBootstrapData $data, bool $dryRun, array &$messages, bool $force = false): bool
    {
        $page = Page::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'home')
            ->where('status', 'published')
            ->first();

        if ($page === null) {
            return false;
        }

        $page->loadMissing('seoMeta');
        $row = $this->registry->get('home');
        if (! is_array($row) || $row === []) {
            return false;
        }

        $vars = [
            'site_name' => $data->siteName,
            'page_name' => '',
            'motorcycle_name' => '',
        ];
        $interp = $this->registry->interpolateRow($row, $vars);

        $payload = [
            'meta_title' => isset($interp['title']) ? trim((string) $interp['title']) : '',
            'meta_description' => isset($interp['description']) ? trim((string) $interp['description']) : '',
            'og_title' => isset($interp['title']) ? trim((string) $interp['title']) : '',
            'og_description' => isset($interp['description']) ? trim((string) $interp['description']) : '',
        ];

        if ($data->representativeOgImageUrl !== null) {
            $payload['og_image'] = $data->representativeOgImageUrl;
        }

        $seo = $page->seoMeta;
        if (! $seo instanceof SeoMeta) {
            $create = ['tenant_id' => $tenant->id, 'seoable_type' => Page::class, 'seoable_id' => $page->id];
            foreach ($payload as $k => $v) {
                if (! TenantSeoMerge::isFilled($v)) {
                    unset($payload[$k]);
                }
            }
            if ($payload === []) {
                return false;
            }
            if (! $dryRun) {
                SeoMeta::query()->create(array_merge($create, $payload));
            }
            $messages[] = 'SeoMeta for home page '.($dryRun ? '(dry-run) ' : '').'created';

            return true;
        }

        $updates = [];
        foreach ($payload as $field => $value) {
            if (! TenantSeoMerge::isFilled($value)) {
                continue;
            }
            if ($force) {
                $updates[$field] = $value;

                continue;
            }
            $current = $seo->{$field} ?? null;
            if (! TenantSeoMerge::isFilled(is_string($current) ? $current : null)) {
                $updates[$field] = $value;
            }
        }

        if ($updates === []) {
            return false;
        }

        if (! $dryRun) {
            $seo->update($updates);
        }
        $messages[] = 'SeoMeta for home page '.($dryRun ? '(dry-run) ' : '').'patched';

        return true;
    }
}
