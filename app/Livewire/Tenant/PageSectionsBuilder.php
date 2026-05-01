<?php

namespace App\Livewire\Tenant;

use App\Contracts\ForcesFullLivewireRender;
use App\Filament\Tenant\PageBuilder\PageSectionAdminSummaryPresenter;
use App\Filament\Tenant\PageBuilder\PageSectionBuilderPresentationEnricher;
use App\Filament\Tenant\Resources\PageResource;
use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Blueprints\Expert\EditorialGalleryBlueprint;
use App\PageBuilder\Blueprints\Expert\ExpertHeroBlueprint;
use App\PageBuilder\Blueprints\Expert\ExpertLeadFormBlueprint;
use App\PageBuilder\Contacts\ContactsInfoDataService;
use App\PageBuilder\DataTableSectionJsonNormalizer;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageBuilderPageContext;
use App\PageBuilder\PageSectionCategory;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Support\PageRichContent;
use App\Tenant\ExpertPr\MagasHeroDefaults;
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

class PageSectionsBuilder extends Component implements ForcesFullLivewireRender, HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTenantPublicFilePicker;

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

    public function forceFullLivewireRender(): void
    {
        $this->forceRender();
    }

    public function mount(Page $record): void
    {
        $this->record = $record;
        $this->resetTransientBuilderState();
        $this->expandedSectionIds = array_values(array_map(
            'intval',
            session()->get($this->builderSessionKey('expanded'), []) ?: []
        ));
        $this->listDensity = session()->get($this->builderSessionKey('density'), 'comfort') === 'compact'
            ? 'compact'
            : 'comfort';
        $this->sectionSearch = (string) session()->get($this->builderSessionKey('search'), '');
        $this->showOnlyHidden = (bool) session()->get($this->builderSessionKey('only_hidden'), false);
    }

    /**
     * Сброс модалок/редактора/вставки при входе на экран (не переносим между страницами).
     */
    private function resetTransientBuilderState(): void
    {
        $this->showEditor = false;
        $this->activeTypeId = null;
        $this->editingSectionId = null;
        $this->sectionFormData = [];
        $this->insertAfterSectionId = null;
        $this->showDeleteModal = false;
        $this->deleteTargetId = null;
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
        session()->put($this->builderSessionKey('only_hidden'), $this->showOnlyHidden);
    }

    public function getPageContextProperty(): PageBuilderPageContext
    {
        return PageBuilderPageContext::fromPage($this->record);
    }

    public function clearInsertAfter(): void
    {
        $this->insertAfterSectionId = null;
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
        $ctx = $this->pageContext;

        $categories = $this->record->slug === 'home'
            ? PageSectionCategory::orderedForCatalog()
            : PageSectionCategory::orderedForContentPageCatalog();

        /** @var array<string, list<array<string, mixed>>> $byCat */
        $byCat = [];
        foreach ($categories as $cat) {
            $byCat[$cat->value] = [];
        }

        foreach ($registry->forPage($this->record, $themeKey) as $bp) {
            $cat = $bp->category();
            if (! isset($byCat[$cat->value])) {
                continue;
            }
            $desc = $ctx->catalogDescriptionForType($bp->id(), $bp->description());
            $byCat[$cat->value][] = [
                'id' => $bp->id(),
                'label' => $ctx->typeLabelForUi($bp->id(), $bp->label()),
                'description' => $this->shortCatalogDescription($desc),
                'icon' => $bp->icon(),
                'category' => $cat->value,
                'category_label' => $cat->label(),
            ];
        }

        $priority = $ctx->isHome
            ? ['hero', 'info_cards', 'cards_teaser', 'motorcycle_catalog', 'content_faq', 'gallery', 'contacts_info']
            : ['hero', 'structured_text', 'text_section', 'rich_text', 'gallery', 'content_faq', 'contacts_info'];

        $frequent = [];
        $pulledIds = [];
        foreach ($priority as $wantId) {
            foreach ($byCat as $catVal => $items) {
                foreach ($items as $idx => $item) {
                    if (($item['id'] ?? '') === $wantId) {
                        $frequent[] = $item;
                        unset($byCat[$catVal][$idx]);
                        $pulledIds[$wantId] = true;
                        break 2;
                    }
                }
            }
        }

        foreach ($byCat as $k => $items) {
            $byCat[$k] = array_values($items);
        }

        $out = collect();
        if ($frequent !== []) {
            $out->push([
                'category' => '_frequent',
                'label' => 'Часто используемые',
                'items' => $frequent,
            ]);
        }

        foreach ($categories as $cat) {
            $items = $byCat[$cat->value] ?? [];
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

    private function shortCatalogDescription(string $description): string
    {
        $t = trim($description);
        if ($t === '') {
            return '';
        }
        if (mb_strlen($t) <= 64) {
            return $t;
        }

        return mb_substr($t, 0, 61).'…';
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
        if ($this->pageContext->isHome) {
            return [
                'excerpt' => '',
                'edit_url' => $this->contentTabUrl,
                'mode' => 'home',
            ];
        }

        $main = $this->record->sections()
            ->where('page_id', $this->record->getKey())
            ->where('section_key', 'main')
            ->first();
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

        return $this->pageContext->isHome
            ? url('/')
            : url('/'.ltrim((string) $this->record->slug, '/'));
    }

    public function getTenantThemeKeyProperty(): string
    {
        return currentTenant()?->themeKey() ?? 'default';
    }

    /**
     * Краткая подсказка для иконки у блока основного текста (вторичный слой, не поток абзацев).
     */
    public function getMainCardSiteTooltipProperty(): ?string
    {
        if ($this->pageContext->isHome) {
            return null;
        }

        $theme = $this->tenantThemeKey;
        $slug = (string) ($this->record->slug ?? '');
        $base = 'Показывается над списком блоков; не перетаскивается.';

        if ($theme === 'moto' && $slug === 'contacts') {
            return $base.' Под заголовком; ссылки оформляются как кнопки.';
        }

        if ($theme === 'moto' && $slug === 'usloviya-arenda') {
            return $base.' Вступление перед разделами и оглавлением.';
        }

        return $base;
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
        $presentationEnricher = app(PageSectionBuilderPresentationEnricher::class);
        $themeKey = currentTenant()?->themeKey() ?? 'default';

        $ctx = $this->pageContext;
        $rows = [];
        $position = 0;
        foreach ($ops->listBuilderSections($this->record) as $section) {
            if ((int) $section->page_id !== (int) $this->record->getKey()) {
                continue;
            }
            $position++;
            $typeId = $legacy->effectiveTypeId($section);
            $typeLabel = $registry->has($typeId) ? $registry->get($typeId)->label() : $typeId;
            $typeLabelUi = $ctx->typeLabelForUi($typeId, $typeLabel);
            $icon = $registry->has($typeId) ? $registry->get($typeId)->icon() : 'heroicon-o-squares-2x2';
            $summary = $presenter->summarize($section, $registry, $legacy);
            $summary = $presentationEnricher->enrich($summary, $this->record, $section, $typeId, $themeKey);
            $summaryArr = $summary->toArray();

            $dataJson = is_array($section->data_json) ? $section->data_json : [];
            $blockTitleQuick = match ($typeId) {
                'structured_text', 'text_section', 'contacts_info', 'content_faq' => (string) ($dataJson['title'] ?? ''),
                'rich_text', 'gallery', 'hero' => (string) ($dataJson['heading'] ?? ''),
                default => null,
            };
            $hasBlockTitleQuick = $blockTitleQuick !== null;

            $blueprintPreview = $registry->has($typeId)
                ? trim((string) $registry->get($typeId)->previewSummary($dataJson))
                : '';

            $cardTitle = trim((string) ($summaryArr['primaryHeadline'] ?? ''));
            if ($cardTitle === '') {
                $cardTitle = trim((string) ($summaryArr['displayTitle'] ?? ''));
            }
            if ($cardTitle === '') {
                $cardTitle = $typeLabelUi;
            }

            $titleNorm = mb_strtolower($cardTitle);
            $cardPreview = $blueprintPreview;
            if ($cardPreview !== '' && mb_strtolower($cardPreview) === $titleNorm) {
                $cardPreview = '';
            }
            $summaryLines = $summaryArr['summaryLines'] ?? [];
            if ($cardPreview === '' && $summaryLines !== []) {
                foreach ($summaryLines as $line) {
                    $t = trim((string) $line);
                    if ($t === '' || mb_strtolower($t) === $titleNorm) {
                        continue;
                    }
                    $cardPreview = $t;
                    break;
                }
            }
            if ($cardPreview === '' && ! empty($summaryArr['badges'])) {
                $cardPreview = trim((string) ($summaryArr['badges'][0] ?? ''));
            }
            if (mb_strlen($cardPreview) > 120) {
                $cardPreview = mb_substr($cardPreview, 0, 117).'…';
            }

            $tipParts = array_filter(
                array_merge(
                    [trim((string) ($summaryArr['onSiteLine'] ?? ''))],
                    $summaryArr['builderNotes'] ?? []
                ),
                static fn (string $p): bool => $p !== ''
            );
            $cardSiteTooltip = $tipParts !== [] ? implode("\n\n", $tipParts) : '';

            $rows[] = [
                'id' => $section->id,
                'section_key' => (string) $section->section_key,
                'type_id' => $typeId,
                'type_label' => $typeLabel,
                'type_label_ui' => $typeLabelUi,
                'icon' => $icon,
                'title' => (string) ($section->title ?? ''),
                'preview' => $blueprintPreview,
                'card_title' => $cardTitle,
                'card_preview' => $cardPreview,
                'card_site_tooltip' => $cardSiteTooltip,
                'summary' => $summaryArr,
                'search_blob' => $summary->searchBlob($typeLabelUi).' '.mb_strtolower($section->section_key),
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
        $this->persistUiSession();
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
        $blueprint = app(PageSectionTypeRegistry::class)->get($typeId);

        $existing = is_array($section->data_json) ? $section->data_json : [];
        if ($typeId === 'expert_hero') {
            $tenantCtx = currentTenant();
            if ($tenantCtx !== null && (string) $tenantCtx->slug === MagasHeroDefaults::SLUG) {
                $existing = app(MagasHeroDefaults::class)->mergeMissingHeroImageForEditor(
                    (int) $tenantCtx->id,
                    $existing,
                );
            }
        }
        $defaults = $blueprint->defaultData();
        if ($typeId === 'data_table') {
            $dataJson = DataTableSectionJsonNormalizer::hydrateForEditor(
                DataTableSectionJsonNormalizer::shallowBaseForMerge($defaults, $existing)
            );
        } else {
            $dataJson = ContactsInfoDataService::mergeDataJsonPreservingChannelList($defaults, $existing);
        }
        if ($typeId === 'contacts_info') {
            $dataJson = app(ContactsInfoDataService::class)->hydrateForEditor($dataJson);
            $dataJson['channels'] = ContactsInfoDataService::normalizeChannelsForRepeater($dataJson['channels'] ?? []);
        }
        if (in_array($typeId, ['contacts_info', 'contacts'], true)) {
            $dataJson = app(ContactsInfoDataService::class)->hydrateMapForEditor($dataJson);
        }
        if ($typeId === 'expert_lead_form') {
            $dataJson = ExpertLeadFormBlueprint::normalizeDataJsonForEditor($dataJson);
        }
        if ($typeId === 'expert_hero') {
            $dataJson = ExpertHeroBlueprint::normalizeHeroPresentationForEditor($dataJson);
        }
        if ($typeId === 'editorial_gallery') {
            $dataJson = EditorialGalleryBlueprint::normalizePresentationForEditor($dataJson);
        }
        $this->sectionFormData = [
            'title' => $section->title ?? '',
            'status' => $section->status,
            'is_visible' => $section->is_visible,
            'data_json' => $dataJson,
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
                    : ($row['title'] ?? $row['type_label_ui'] ?? $row['type_label'] ?? null);
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
