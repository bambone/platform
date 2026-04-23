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
 *
 * Осознанное наследование: при {@code theme_key = black_duck} после слоя {@code tenant.themes.black_duck.*}
 * подставляется {@code tenant.themes.expert_auto.*}, затем default — чтобы не дублировать весь набор partials;
 * отсутствие black_duck-шаблона не падает сразу (см. лог {@code tenancy.log_view_resolution}).
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
        $requestScopedKey = 'tenant_view_resolved:'.($tenant?->id ?? 0).':'.$logicalName;
        $attrs = request()->attributes;
        if ($attrs->has($requestScopedKey)) {
            return $attrs->get($requestScopedKey);
        }

        $themeKeyNormalized = $tenant === null ? 'default' : $tenant->themeKey();
        $themeKeyRaw = $tenant === null ? null : ($tenant->getAttributes()['theme_key'] ?? $tenant->theme_key);

        $candidates = [];
        if ($themeKeyNormalized !== '') {
            $candidates[] = "tenant.themes.{$themeKeyNormalized}.{$logicalName}";
        }
        if ($themeKeyNormalized === 'black_duck') {
            $candidates[] = "tenant.themes.expert_auto.{$logicalName}";
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

        $attrs->set($requestScopedKey, $resolved);

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

    /**
     * Safely checks if a tenant view exists for the logical name without throwing an exception if not found.
     */
    public function exists(string $logicalName, ?Tenant $tenant = null): bool
    {
        try {
            $this->assertValidLogicalName($logicalName);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        $tenant ??= tenant();
        $themeKeyNormalized = $tenant === null ? 'default' : $tenant->themeKey();

        $candidates = [];
        if ($themeKeyNormalized !== '') {
            $candidates[] = "tenant.themes.{$themeKeyNormalized}.{$logicalName}";
        }
        if ($themeKeyNormalized === 'black_duck') {
            $candidates[] = "tenant.themes.expert_auto.{$logicalName}";
        }
        $candidates[] = "tenant.themes.default.{$logicalName}";
        $candidates[] = "tenant.{$logicalName}";

        foreach (array_values(array_unique($candidates)) as $view) {
            if (View::exists($view)) {
                return true;
            }
        }

        return false;
    }
}
