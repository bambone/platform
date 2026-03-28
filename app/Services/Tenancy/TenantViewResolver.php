<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
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
    public function resolve(string $logicalName, ?Tenant $tenant = null): string
    {
        $logicalName = trim($logicalName);
        if ($logicalName === '') {
            throw new InvalidArgumentException('Tenant view logical name must not be empty.');
        }

        $tenant ??= tenant();
        $themeKey = $tenant === null ? 'default' : $tenant->themeKey();

        $candidates = [];
        if ($themeKey !== '') {
            $candidates[] = "tenant.themes.{$themeKey}.{$logicalName}";
        }
        $candidates[] = "tenant.themes.default.{$logicalName}";
        $candidates[] = "tenant.{$logicalName}";

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $view) {
            if (View::exists($view)) {
                return $view;
            }
        }

        throw new InvalidArgumentException(
            'No tenant view found for logical name "'.$logicalName.'" (tried: '.implode(', ', $candidates).').'
        );
    }
}
