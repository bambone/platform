<?php

namespace App\Filament\Tenant\PageBuilder;

use App\Models\PageSection;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionTypeRegistry;

final class PageSectionAdminSummaryPresenter
{
    public function summarize(
        PageSection $section,
        PageSectionTypeRegistry $registry,
        LegacySectionTypeResolver $legacy,
    ): SectionAdminSummary {
        $typeId = $section->section_type;
        if (! is_string($typeId) || $typeId === '' || ! $registry->has($typeId)) {
            $typeId = $legacy->effectiveTypeId($section);
        }

        if ($registry->has($typeId)) {
            return $registry->get($typeId)->adminSummary($section);
        }

        return SectionAdminSummary::fallbackUnknown($section, $typeId);
    }
}
