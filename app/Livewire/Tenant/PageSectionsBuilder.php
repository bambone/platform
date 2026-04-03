<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionCategory;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Services\PageBuilder\PageSectionOperationsService;
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
     * Alias for {@see getBuilderRowsProperty()} — секции текущей страницы (без `main` в БД).
     *
     * @return list<array{id: int, type_id: string, type_label: string, title: string, preview: string, sort_order: int, position: int, is_visible: bool, status: string}>
     */
    public function getCurrentSectionsProperty(): array
    {
        return $this->builderRows;
    }

    /**
     * Alias for {@see getCatalogGroupedProperty()}.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAvailableSectionCatalogProperty(): Collection
    {
        return $this->catalogGrouped;
    }

    /**
     * @return list<array{id: int, type_id: string, type_label: string, title: string, preview: string, sort_order: int, position: int, is_visible: bool, status: string}>
     */
    public function getBuilderRowsProperty(): array
    {
        $ops = app(PageSectionOperationsService::class);
        $registry = app(PageSectionTypeRegistry::class);
        $legacy = app(LegacySectionTypeResolver::class);

        $rows = [];
        $position = 0;
        foreach ($ops->listBuilderSections($this->record) as $section) {
            $position++;
            $typeId = $legacy->effectiveTypeId($section);
            $label = $registry->has($typeId) ? $registry->get($typeId)->label() : $typeId;
            $data = is_array($section->data_json) ? $section->data_json : [];
            $preview = $registry->has($typeId)
                ? $registry->get($typeId)->previewSummary($data)
                : '';

            $rows[] = [
                'id' => $section->id,
                'type_id' => $typeId,
                'type_label' => $label,
                'title' => (string) ($section->title ?? ''),
                'preview' => $preview,
                'sort_order' => (int) $section->sort_order,
                'position' => $position,
                'is_visible' => (bool) $section->is_visible,
                'status' => (string) $section->status,
            ];
        }

        return $rows;
    }

    public function startAdd(string $typeId): void
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
        $this->sectionFormData = [
            'title' => $blueprint->label(),
            'status' => 'published',
            'is_visible' => true,
            'data_json' => $blueprint->defaultData(),
        ];
        $this->showEditor = true;
        $this->cacheSchema('sectionEditor', null);
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
        $this->cacheSchema('sectionEditor', null);
    }

    public function save(): void
    {
        $tenantId = currentTenant()?->id;
        $this->sectionEditor->validate();
        $data = $this->sectionEditor->getState();
        $ops = app(PageSectionOperationsService::class);

        try {
            if ($this->editingSectionId === null) {
                if ($this->activeTypeId === null) {
                    return;
                }
                $ops->createTypedSection($this->record, $this->activeTypeId, $data, $tenantId);
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

        $this->closeEditor();
    }

    public function delete(int $sectionId): void
    {
        $tenantId = currentTenant()?->id;
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

    }

    public function duplicate(int $sectionId): void
    {
        $tenantId = currentTenant()?->id;
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

    }

    public function toggleVisibility(int $sectionId): void
    {
        $tenantId = currentTenant()?->id;
        $section = PageSection::query()
            ->where('page_id', $this->record->id)
            ->whereKey($sectionId)
            ->firstOrFail();

        try {
            app(PageSectionOperationsService::class)->toggleVisibility($section, $tenantId);
        } catch (\Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }

    }

    public function moveUp(int $sectionId): void
    {
        $this->move($sectionId, -1);
    }

    public function moveDown(int $sectionId): void
    {
        $this->move($sectionId, 1);
    }

    private function move(int $sectionId, int $dir): void
    {
        $tenantId = currentTenant()?->id;
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

    }

    public function render(): View
    {
        return view('livewire.tenant.page-sections-builder');
    }
}
