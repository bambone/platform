<?php

namespace App\Services\PageBuilder;

use App\Models\PageSection;
use App\Models\Tenant;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionTypeRegistry;
use Illuminate\Support\Facades\View;

/**
 * Секции страницы: для {@code black_duck} кандидаты включают {@code tenant.themes.expert_auto.*} после слоя темы
 * (та же модель наследования, что и {@see TenantViewResolver}).
 */
final class SectionViewResolver
{
    public function __construct(
        private readonly LegacySectionTypeResolver $legacyResolver,
        private readonly PageSectionTypeRegistry $registry,
    ) {}

    /**
     * Resolved Blade view name for a section row, or null if none (caller may legacy-render).
     */
    public function resolveViewName(PageSection $section, ?Tenant $tenant = null): ?string
    {
        $typeId = $this->legacyResolver->effectiveTypeId($section);
        if (! $this->registry->has($typeId)) {
            return null;
        }
        $logical = $this->registry->get($typeId)->viewLogicalName();

        $tenant ??= tenant();
        $themeKey = $tenant?->themeKey() ?? 'default';

        if (! $this->registry->get($typeId)->supportsTheme($themeKey)) {
            return null;
        }

        $candidates = [];
        if ($themeKey !== '') {
            $candidates[] = "tenant.themes.{$themeKey}.{$logical}";
        }
        if ($themeKey === 'black_duck') {
            $candidates[] = "tenant.themes.expert_auto.{$logical}";
        }
        $candidates[] = "tenant.themes.default.{$logical}";
        $candidates[] = "tenant.{$logical}";

        foreach (array_unique($candidates) as $view) {
            if (View::exists($view)) {
                return $view;
            }
        }

        return null;
    }
}
