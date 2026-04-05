<?php

namespace App\Services\Seo;

use App\Services\Seo\Data\TenantSeoBootstrapData;

/**
 * Builds default {@see TenantSetting} `seo.route_overrides` JSON object from {@see config('seo_autopilot.route_overrides_allowlist')}.
 */
final class TenantSeoRouteOverridesBuilder
{
    public function __construct(
        private SeoRouteRegistry $registry,
    ) {}

    /**
     * @return array<string, array<string, string>>
     */
    public function build(TenantSeoBootstrapData $data): array
    {
        $allowlist = config('seo_autopilot.route_overrides_allowlist', []);
        $allowlist = is_array($allowlist) ? $allowlist : [];

        $vars = [
            'site_name' => $data->siteName,
            'page_name' => '',
            'motorcycle_name' => '',
        ];

        $out = [];
        foreach ($allowlist as $routeName) {
            if (! is_string($routeName) || $routeName === '') {
                continue;
            }
            $row = $this->registry->get($routeName);
            if (! is_array($row) || $row === []) {
                continue;
            }
            $interpolated = $this->registry->interpolateRow($row, $vars);
            $clean = [];
            foreach (['title', 'description', 'h1'] as $k) {
                if (isset($interpolated[$k]) && is_string($interpolated[$k]) && trim($interpolated[$k]) !== '') {
                    $clean[$k] = trim($interpolated[$k]);
                }
            }
            if ($clean !== []) {
                $out[$routeName] = $clean;
            }
        }

        return $out;
    }
}
