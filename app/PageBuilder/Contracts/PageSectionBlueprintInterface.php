<?php

namespace App\PageBuilder\Contracts;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Component;

interface PageSectionBlueprintInterface
{
    public function id(): string;

    public function label(): string;

    public function description(): string;

    public function icon(): string;

    public function category(): PageSectionCategory;

    /**
     * @return array<string, mixed>
     */
    public function defaultData(): array;

    /**
     * Filament form components; state paths use `title`, `status`, `is_visible`, `data_json.*`.
     *
     * @return array<int, Component>
     */
    public function formComponents(): array;

    /**
     * Logical tenant view name (e.g. sections.hero).
     */
    public function viewLogicalName(): string;

    public function supportsTheme(string $themeKey): bool;

    public function previewSummary(array $data): string;

    /**
     * Plain-text admin card summary (list UI, delete confirm).
     */
    public function adminSummary(PageSection $section): SectionAdminSummary;
}
