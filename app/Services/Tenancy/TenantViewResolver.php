<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Resolves tenant public Blade views using theme_key with a fixed logical name contract.
 *
 * Logical names use dots without the leading "tenant." prefix, e.g. "pages.home", "booking.index".
 * Themed: tenant.themes.{theme_key}.{logical}
 * Default theme layer: tenant.themes.default.{logical}
 * Engine fallback: tenant.{logical}
 */
final class TenantViewResolver
{
    private const LOGICAL_MAX_LEN = 160;

    public function resolve(string $logicalName, ?Tenant $tenant = null): string
    {
        $logicalName = trim($logicalName);
        if ($logicalName === '') {
            throw new InvalidArgumentException('Tenant view logical name must not be empty.');
        }

        $this->assertValidLogicalName($logicalName);

        $tenant ??= tenant();
        $themeKeyNormalized = $tenant === null ? 'default' : $tenant->themeKey();
        $themeKeyRaw = $tenant === null ? null : ($tenant->getAttributes()['theme_key'] ?? $tenant->theme_key);

        $candidates = [];
        if ($themeKeyNormalized !== '') {
            $candidates[] = "tenant.themes.{$themeKeyNormalized}.{$logicalName}";
        }
        $candidates[] = "tenant.themes.default.{$logicalName}";
        $candidates[] = "tenant.{$logicalName}";

        $candidates = array_values(array_unique($candidates));

        $rejected = [];
        $resolved = null;
        foreach ($candidates as $view) {
            if (View::exists($view)) {
                $resolved = $view;

                break;
            }
            $rejected[] = $view;
        }

        if ($resolved === null) {
            throw new InvalidArgumentException(
                'No tenant view found for logical name "'.$logicalName.'" (tried: '.implode(', ', $candidates).').'
            );
        }

        if (config('tenancy.log_view_resolution') === true) {
            Log::debug('tenant_view_resolved', [
                'tenant_id' => $tenant?->id,
                'theme_key_raw' => $themeKeyRaw,
                'theme_key_normalized' => $themeKeyNormalized,
                'logical' => $logicalName,
                'resolved' => $resolved,
                'skipped_candidates' => $rejected,
            ]);
        }

        return $resolved;
    }

    private function assertValidLogicalName(string $logicalName): void
    {
        if (strlen($logicalName) > self::LOGICAL_MAX_LEN) {
            throw new InvalidArgumentException('Tenant view logical name exceeds maximum length.');
        }

        if (str_contains($logicalName, '..')) {
            throw new InvalidArgumentException('Invalid tenant view logical name.');
        }

        if (! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $logicalName)) {
            throw new InvalidArgumentException('Invalid tenant view logical name.');
        }
    }
}
