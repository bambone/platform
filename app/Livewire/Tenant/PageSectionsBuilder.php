<?php

namespace App\Livewire\Tenant;

use App\Filament\Tenant\PageBuilder\PageSectionAdminSummaryPresenter;
use App\Filament\Tenant\Resources\PageResource;
use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionCategory;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Support\PageRichContent;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

class PageSectionsBuilder extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTenantPublicFilePicker;
    use WithFileUploads;

    public Page $record;

    public bool $showEditor = false;

    public ?string $activeTypeId = null;

    public ?int $editingSectionId = null;

    /** @var array<string, mixed> */
    public array $sectionFormData = [];

    /** After which section to insert a new block (null = end of list). */
    public ?int $insertAfterSectionId = null;

    /** @var list<int> */
    public array $expandedSectionIds = [];

    public string $listDensity = 'comfort';

    public string $sectionSearch = '';

    public bool $showOnlyHidden = false;

    public bool $showDeleteModal = false;

    public ?int $deleteTargetId = null;

    public function mount(Page $record): void
    {
        $this->record = $record;
        $this->expandedSectionIds = array_values(array_map(
            'intval',
            session()->get($this->builderSessionKey('expanded'), []) ?: []
        ));
        $this->listDensity = session()->get($this->builderSessionKey('density'), 'comfort') === 'compact'
            ? 'compact'
            : 'comfort';
        $this->sectionSearch = (string) session()->get($this->builderSessionKey('search'), '');
    }

    private function builderSessionKey(string $suffix): string
    {
        $tenantId = currentTenant()?->id ?? 0;

        return "page_sections_builder.{$tenantId}.{$this->record->getKey()}.{$suffix}";
    }

    private function persistUiSession(): void
    {
        session()->put($this->builderSessionKey('expanded'), $this->expandedSectionIds);
        session()->put($this->builderSessionKey('density'), $this->listDensity);
        session()->put($this->builderSessionKey('search'), $this->sectionSearch);
    }

    private function tenantIdForOps(): ?int
    {
        return currentTenant()?->id ?? ($this->record->tenant_id ? (int) $this->record->tenant_id : null);
    }

    public function sectionEditor(Schema $schema): Schema
    {
        if ($this->activeTypeId === null) {
            return $schema->components([])->statePath('sectionFormData');
        }

        $registry = app(PageSectionTypeRegistry::class);
        $blueprint = $registry->get($this->activeTypeId);

        return $schema
            ->components([
                SchemaSection::make()
                    ->schema(array_merge([
                        TextInput::make('title')
                            ->label('Подпись в списке')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Статус')
                            ->options(PageSection::statuses())
                            ->required()
                            ->native(true),
                        Toggle::make('is_visible')
                            ->label('Показывать на сайте')
                            ->default(true),
                    ], $blueprint->formComponents()))
                    ->columns(2),
            ])
            ->statePath('sectionFormData');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getCatalogGroupedProperty(): Collection
    {
        $tenant = currentTenant();
        $themeKey = $tenant?->themeKey() ?? 'default';
        $registry = app(PageSectionTypeRegistry::class);

        $categories = $this->record->slug === 'home'
            ? PageSectionCategory::orderedForCatalog()
            : PageSectionCategory::orderedForContentPageCatalog();

        $out = collect();
        foreach ($categories as $cat) {
            $items = [];
            foreach ($registry->forPage($this->record, $themeKey) as $bp) {
                if ($bp->category() !== $cat) {
                    continue;
                }
                $items[] = [
                    'id' => $bp->id(),
                    'label' => $bp->label(),
                    'description' => $bp->description(),
                    'icon' => $bp->icon(),
                    'category' => $cat->value,
                    'category_label' => $cat->label(),
                ];
            }
            if ($items !== []) {
                $out->push([
                    'category' => $cat->value,
                    'label' => $cat->label(),
                    'items' => $items,
                ]);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCurrentSectionsProperty(): array
    {
        return $this->filteredBuilderRows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getAvailableSectionCatalogProperty(): Collection
    {
        return $this->catalogGrouped;
    }

    /**
     * @return array{status_published: int, hidden_on_site: int, total: int}
     */
    public function getSectionMetricsProperty(): array
    {
        $rows = $this->builderRows;
        $total = count($rows);
        $statusPublished = 0;
        $hiddenOnSite = 0;
        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'published') {
                $statusPublished++;
            }
            if (! ($row['is_visible'] ?? true)) {
                $hiddenOnSite++;
            }
        }

        return [
            'status_published' => $statusPublished,
            'hidden_on_site' => $hiddenOnSite,
            'total' => $total,
        ];
    }

    /**
     * @return ?array{excerpt: string, edit_url: string, mode: string}
     */
    public function getMainCardProperty(): ?array
    {
        if ($this->record->slug === 'home') {
            return [
                'excerpt' => '',
                'edit_url' => $this->contentTabUrl,
                'mode' => 'home',
            ];
        }

        $main = $this->record->sections()->where('section_key', 'main')->first();
        $raw = is_array($main?->data_json) ? ($main->data_json['content'] ?? '') : '';

        return [
            'excerpt' => PageRichContent::toPlainTextExcerpt($raw, 220),
            'edit_url' => $this->contentTabUrl,
            'mode' => 'content',
        ];
    }

    public function getContentTabUrlProperty(): string
    {
        $url = PageResource::getUrl('edit', ['record' => $this->record]);

        return $url.(str_contains($url, '?') ? '&' : '?').'relation=';
    }

    public function getPublicPageUrlProperty(): ?string
    {
        if ($this->record->status !== 'published') {
            return null;
        }

        return $this->record->slug === 'home'
            ? url('/')
            : url('/'.ltrim((string) $this->record->slug, '/'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBuilderRowsProperty(): array
    {
        $ops = app(PageSectionOperationsService::class);
        $registry = app(PageSectionTypeRegistry::class);
        $legacy = app(LegacySectionTypeResolver::class);
        $presenter = app(PageSectionAdminSummaryPresenter::class);

        $rows = [];
        $position = 0;
        foreach ($ops->listBuilderSections($this->record) as $section) {
            $position++;
            $typeId = $legacy->effectiveTypeId($section);
            $typeLabel = $registry->has($typeId) ? $registry->get($typeId)->label() : $typeId;
            $icon = $registry->has($typeId) ? $registry->get($typeId)->icon() : 'heroicon-o-squares-2x2';
            $summary = $presenter->summarize($section, $registry, $legacy);
            $summaryArr = $summary->toArray();

            $dataJson = is_array($section->data_json) ? $section->data_json : [];
            $blockTitleQuick = match ($typeId) {
                'structured_text', 'text_section', 'contacts_info', 'content_faq' => (string) ($dataJson['title'] ?? ''),
                'rich_text', 'gallery', 'hero' => (string) ($dataJson['heading'] ?? ''),
                default => null,
            };
            $hasBlockTitleQuick = $blockTitleQuick !== null;

            $rows[] = [
                'id' => $section->id,
                'section_key' => (string) $section->section_key,
                'type_id' => $typeId,
                'type_label' => $typeLabel,
                'icon' => $icon,
                'title' => (string) ($section->title ?? ''),
                'preview' => $registry->has($typeId)
                    ? $registry->get($typeId)->previewSummary($dataJson)
                    : '',
                'summary' => $summaryArr,
                'search_blob' => $summary->searchBlob($typeLabel).' '.mb_strtolower($section->section_key),
                'sort_order' => (int) $section->sort_order,
                'position' => $position,
                'is_visible' => (bool) $section->is_visible,
                'status' => (string) $section->status,
                'has_block_title_quick' => $hasBlockTitleQuick,
                'block_title_quick_value' => $hasBlockTitleQuick ? $blockTitleQuick : '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFilteredBuilderRowsProperty(): array
    {
        $rows = $this->builderRows;
        $q = mb_strtolower(trim($this->sectionSearch));
        $out = [];
        foreach ($rows as $row) {
            if ($this->showOnlyHidden && ($row['is_visible'] ?? true)) {
                continue;
            }
            if ($q !== '' && ! str_contains($row['search_blob'] ?? '', $q)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public function updatedSectionSearch(): void
    {
        $this->persistUiSession();
    }

    public function updatedListDensity(): void
    {
        $this->listDensity = $this->listDensity === 'compact' ? 'compact' : 'comfort';
        $this->persistUiSession();
    }

    public function updatedShowOnlyHidden(): void
    {
        // no session for checkbox — optional; could add
    }

    public function isExpanded(int $sectionId): bool
    {
        return in_array($sectionId, $this->expandedSectionIds, true);
    }

    public function toggleExpanded(int $sectionId): void
    {
        $ids = $this->expandedSectionIds;
        $idx = array_search($sectionId, $ids, true);
        if ($idx !== false) {
            unset($ids[$idx]);
            $this->expandedSectionIds = array_values($ids);
        } else {
            $this->expandedSectionIds[] = $sectionId;
        }
        $this->persistUiSession();
    }

    public function expandAllSections(): void
    {
        $this->expandedSectionIds = array_map(
            fn (array $r): int => (int) $r['id'],
            $this->builderRows
        );
        $this->persistUiSession();
    }

    public function collapseAllSections(): void
    {
        $this->expandedSectionIds = [];
        $this->persistUiSession();
    }

    public function startAdd(string $typeId, ?int $afterSectionId = null): void
    {
        $tenant = currentTenant();
        $themeKey = $tenant?->themeKey() ?? 'default';
        $registry = app(PageSectionTypeRegistry::class);
        if (! $registry->has($typeId) || ! $registry->get($typeId)->supportsTheme($themeKey)) {
            Notification::make()
                ->title('Тип недоступен')
                ->body('Этот блок не поддерживается текущей темой сайта.')
                ->warning()
                ->send();

            return;
        }

        if (! $registry->typeAllowedOnPage($typeId, $this->record, $themeKey)) {
            Notification::make()
                ->title('Тип недоступен для этой страницы')
                ->body('Этот блок нельзя добавить на текущую страницу. Для главной и обычных страниц разные наборы секций.')
                ->warning()
                ->send();

            return;
        }

        $blueprint = $registry->get($typeId);
        $this->activeTypeId = $typeId;
        $this->editingSectionId = null;
        $this->insertAfterSectionId = $afterSectionId;
        $this->sectionFormData = [
            'title' => $blueprint->label(),
            'status' => 'published',
            'is_visible' => true,
            'data_json' => $blueprint->defaultData(),
        ];
        $this->showEditor = true;
        $this->cacheSchema('sectionEditor', null);
    }

    public function startAddToEnd(): void
    {
        $this->insertAfterSectionId = null;
        $this->js('document.getElementById("page-section-catalog")?.scrollIntoView({behavior:"smooth", block:"start"})');
    }

    public function startAddBelow(int $sectionId): void
    {
        $this->insertAfterSectionId = $sectionId;
        $this->js('document.getElementById("page-section-catalog")?.scrollIntoView({behavior:"smooth", block:"start"})');
    }

    public function startEdit(int $sectionId): void
    {
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        if ($section->section_key === 'main') {
            return;
        }

        $legacy = app(LegacySectionTypeResolver::class);
        $typeId = $section->section_type;
        if (! is_string($typeId) || $typeId === '' || ! app(PageSectionTypeRegistry::class)->has($typeId)) {
            $typeId = $legacy->effectiveTypeId($section);
        }

        if (! app(PageSectionTypeRegistry::class)->has($typeId)) {
            Notification::make()
                ->title('Редактирование недоступно')
                ->body('Для этой секции нет типизированной формы. Используйте данные из шаблона или миграции.')
                ->warning()
                ->send();

            return;
        }

        $this->activeTypeId = $typeId;
        $this->editingSectionId = $section->id;
        $this->insertAfterSectionId = null;
        $this->sectionFormData = [
            'title' => $section->title ?? '',
            'status' => $section->status,
            'is_visible' => $section->is_visible,
            'data_json' => is_array($section->data_json) ? $section->data_json : app(PageSectionTypeRegistry::class)->get($typeId)->defaultData(),
        ];
        $this->showEditor = true;
        $this->cacheSchema('sectionEditor', null);
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
        $this->activeTypeId = null;
        $this->editingSectionId = null;
        $this->sectionFormData = [];
        $this->insertAfterSectionId = null;
        $this->cacheSchema('sectionEditor', null);
    }

    public function save(): void
    {
        $tenantId = $this->tenantIdForOps();
        $this->sectionEditor->validate();
        $data = $this->sectionEditor->getState();
        $ops = app(PageSectionOperationsService::class);

        try {
            if ($this->editingSectionId === null) {
                if ($this->activeTypeId === null) {
                    return;
                }
                $after = $this->insertAfterSectionId;
                $ops->createTypedSection($this->record, $this->activeTypeId, $data, $tenantId, $after);
                Notification::make()->title('Секция добавлена')->success()->send();
            } else {
                $section = PageSection::query()
                    ->where('page_id', $this->record->id)
                    ->whereKey($this->editingSectionId)
                    ->firstOrFail();
                $ops->updateTypedSection($section, $data, $tenantId);
                Notification::make()->title('Секция сохранена')->success()->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        $this->closeEditor();
        $this->persistUiSession();
    }

    public function openDeleteModal(int $sectionId): void
    {
        $this->deleteTargetId = $sectionId;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteTargetId = null;
    }

    public function confirmDelete(): void
    {
        if ($this->deleteTargetId === null) {
            return;
        }
        $id = $this->deleteTargetId;
        $this->closeDeleteModal();
        $this->delete($id);
    }

    public function delete(int $sectionId): void
    {
        $tenantId = $this->tenantIdForOps();
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        try {
            app(PageSectionOperationsService::class)->deleteSection($section, $tenantId);
            Notification::make()->title('Секция удалена')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Нельзя удалить')->body($e->getMessage())->warning()->send();
        }

        $this->expandedSectionIds = array_values(array_filter(
            $this->expandedSectionIds,
            fn (int $i): bool => $i !== $sectionId
        ));
        $this->record->refresh();
        $this->persistUiSession();
    }

    public function duplicate(int $sectionId): void
    {
        $tenantId = $this->tenantIdForOps();
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        try {
            app(PageSectionOperationsService::class)->duplicateSection($section, $tenantId);
            Notification::make()->title('Секция скопирована')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }

        $this->record->refresh();
    }

    public function toggleVisibility(int $sectionId): void
    {
        $tenantId = $this->tenantIdForOps();
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        try {
            app(PageSectionOperationsService::class)->toggleVisibility($section, $tenantId);
        } catch (\Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }

        $this->record->refresh();
    }

    /**
     * @param  array{title?: string, status?: string, is_visible?: bool, block_title?: string}  $payload
     */
    public function patchSectionMeta(int $sectionId, array $payload): void
    {
        $tenantId = $this->tenantIdForOps();
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        try {
            app(PageSectionOperationsService::class)->patchSectionMeta($section, $payload, $tenantId);
        } catch (\Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }

        $this->record->refresh();
    }

    public function moveUp(int $sectionId): void
    {
        $this->move($sectionId, -1);
    }

    public function moveDown(int $sectionId): void
    {
        $this->move($sectionId, 1);
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    public function reorderSections(array $orderedIds): void
    {
        $tenantId = $this->tenantIdForOps();
        try {
            app(PageSectionOperationsService::class)->reorderSections($this->record, $orderedIds, $tenantId);
        } catch (\Throwable $e) {
            Notification::make()->title('Не удалось изменить порядок')->body($e->getMessage())->danger()->send();

            return;
        }
        $this->record->refresh();
        $this->persistUiSession();
    }

    private function move(int $sectionId, int $dir): void
    {
        $tenantId = $this->tenantIdForOps();
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        try {
            if ($dir < 0) {
                app(PageSectionOperationsService::class)->moveSectionUp($section, $tenantId);
            } else {
                app(PageSectionOperationsService::class)->moveSectionDown($section, $tenantId);
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }

        $this->record->refresh();
    }

    public function getSortableEnabledProperty(): bool
    {
        return trim($this->sectionSearch) === '' && ! $this->showOnlyHidden;
    }

    public function getInsertAfterSectionLabelProperty(): ?string
    {
        if ($this->insertAfterSectionId === null) {
            return null;
        }
        foreach ($this->builderRows as $row) {
            if ((int) $row['id'] === $this->insertAfterSectionId) {
                $s = $row['summary'] ?? [];

                return is_array($s) && isset($s['displayTitle']) && (string) $s['displayTitle'] !== ''
                    ? (string) $s['displayTitle']
                    : ($row['title'] ?? $row['type_label'] ?? null);
            }
        }

        return null;
    }

    /**
     * Row for delete modal (must stay stable while modal open).
     *
     * @return ?array<string, mixed>
     */
    public function getDeleteTargetRowProperty(): ?array
    {
        if ($this->deleteTargetId === null) {
            return null;
        }
        foreach ($this->builderRows as $row) {
            if ((int) $row['id'] === $this->deleteTargetId) {
                return $row;
            }
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.tenant.page-sections-builder');
    }
}
