@php
    use App\Models\PageSection;

    $sections = $this->currentSections;
    $allRows = $this->builderRows;
    $sectionCount = count($allRows);
    $metrics = $this->sectionMetrics;
    $catalog = $this->availableSectionCatalog;
    $mainCard = $this->mainCard;
    $publicUrl = $this->publicPageUrl;
    $contentTabUrl = $this->contentTabUrl;
    $sortableEnabled = $this->sortableEnabled;
    $deleteRow = $this->deleteTargetRow;
    $insertAfterId = $this->insertAfterSectionId;
    $insertAfterLabel = $this->insertAfterSectionLabel;
    $pageCtx = $this->pageContext;
    $tenantThemeKey = $this->tenantThemeKey;
    $mainCardSiteTooltip = $this->mainCardSiteTooltip;

    $statusShort = static function (string $key): string {
        return match ($key) {
            'published' => 'Опубл.',
            'draft' => 'Черн.',
            'hidden' => 'Выкл.',
            default => $key,
        };
    };
@endphp

<div class="page-sections-builder-root psb-root fi-section p-4 sm:p-6" wire:key="psb-page-{{ $record->getKey() }}">
    <header class="psb-header sticky top-0 z-10 mb-3 pb-3">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold tracking-tight psb-text-primary sm:text-lg">{{ $record->name }}</h3>
                    <span class="rounded-md border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide psb-context-badge">{{ $pageCtx->modeLabel }}</span>
                </div>
                <p class="mt-0.5 text-[11px] psb-text-muted">{{ $pageCtx->modeHint }}</p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] psb-text-muted">
                    @if($record->slug)
                        <code class="font-mono text-[10px] psb-text-secondary">{{ $record->slug === 'home' ? '/' : '/'.ltrim((string) $record->slug, '/') }}</code>
                        <span aria-hidden="true">·</span>
                    @endif
                    <span>{{ $metrics['total'] }} блок.</span>
                    <span>{{ $metrics['status_published'] }} опубл.</span>
                    @if($metrics['hidden_on_site'] > 0)
                        <span>{{ $metrics['hidden_on_site'] }} скр.</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                @if($publicUrl)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer" class="psb-btn-secondary !w-auto px-3 py-2 text-xs">
                        Сайт ↗
                    </a>
                @endif
                <button
                    type="button"
                    wire:click="startAddToEnd"
                    @if(($record->slug ?? null) === 'home')
                        data-setup-action="pages.home.add_block"
                    @endif
                    class="fi-btn fi-btn-color-primary inline-flex min-h-9 items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-white"
                >
                    Блок
                </button>
                <a href="{{ $contentTabUrl }}" wire:navigate class="psb-btn-secondary !w-auto px-3 py-2 text-xs">
                    Настройки
                </a>
            </div>
        </div>

        <div class="mt-2 flex flex-col gap-2 rounded-lg border px-3 py-2 sm:flex-row sm:flex-wrap sm:items-center" style="border-color: var(--psb-border); background: var(--psb-bg-surface)">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <input
                    id="page-section-search-{{ $this->getId() }}"
                    type="search"
                    wire:model.live.debounce.300ms="sectionSearch"
                    placeholder="Поиск блоков…"
                    aria-label="Поиск по блокам"
                    class="psb-search block w-full py-1.5 text-sm"
                />
            </div>
            <label class="inline-flex cursor-pointer items-center gap-2 text-[11px] psb-text-secondary">
                <input type="checkbox" wire:model.live="showOnlyHidden" class="rounded border-gray-300 text-primary-600 dark:border-white/20" />
                Скрытые
            </label>
            <select wire:model.live="listDensity" class="psb-search w-auto py-1.5 text-[11px]" aria-label="Плотность списка">
                <option value="comfort">С отступами</option>
                <option value="compact">Компактно</option>
            </select>
            <span class="hidden h-4 w-px sm:inline-block" style="background: var(--psb-border)"></span>
            <button type="button" wire:click="expandAllSections" class="text-[11px] font-medium psb-text-secondary hover:underline">Все ▼</button>
            <button type="button" wire:click="collapseAllSections" class="text-[11px] font-medium psb-text-secondary hover:underline">Все ▲</button>
        </div>
        @if(!$sortableEnabled)
            <p class="mt-1.5 text-[10px] psb-text-muted">Перетаскивание выключено при поиске или фильтре.</p>
        @endif
    </header>

    <div class="space-y-0" role="region" aria-label="Структура страницы на сайте">

        <p class="psb-outline-label mb-2 text-[10px] font-semibold uppercase tracking-wider psb-text-muted">Основной текст</p>
        <div class="psb-main-card psb-main-surface p-4 sm:p-5 dark:bg-white/[0.02]">
            <div class="flex flex-wrap items-start gap-4">
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 dark:bg-gray-800 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5 psb-text-primary opacity-80" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="text-[15px] font-semibold psb-text-primary">
                            @if($pageCtx->isHome)
                                Главная — только блоки ниже
                            @else
                                Основной текст страницы
                            @endif
                        </h4>
                        @if($mainCardSiteTooltip)
                            <span class="inline-flex cursor-help" title="{{ $mainCardSiteTooltip }}">
                                <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 psb-text-muted" />
                            </span>
                        @endif
                    </div>
                    @if($mainCard['mode'] === 'home')
                        <p class="mt-1 text-xs psb-text-muted">Текст главной не хранится здесь. Настройте его в свойствах страницы.</p>
                    @else
                        @if($mainCard['excerpt'] !== '')
                            <p class="mt-2 text-sm leading-relaxed psb-text-secondary line-clamp-2">{{ $mainCard['excerpt'] }}</p>
                        @else
                            <p class="mt-1.5 text-xs psb-text-muted">Содержание пока не заполнено.</p>
                        @endif
                    @endif
                    <div class="mt-4">
                        <a href="{{ $mainCard['edit_url'] }}" wire:navigate class="fi-btn fi-color-custom fi-btn-color-primary inline-flex min-h-8 items-center justify-center rounded-lg px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition" style="background-color: var(--primary-600); --fi-color: var(--primary-600)">
                            @if($pageCtx->isHome)
                                Настройки страницы
                            @else
                                Редактировать текст
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-1.5" aria-hidden="true">
            <div class="h-3 w-px" style="background: var(--psb-border)"></div>
        </div>

        <p class="psb-outline-label mb-2 mt-0.5 text-[10px] font-semibold uppercase tracking-wider psb-text-muted">Блоки (порядок на сайте)</p>

        @if($sectionCount === 0)
            <div class="psb-empty p-5 text-center sm:p-6">
                <p class="text-sm font-medium psb-text-primary">Нет блоков</p>
                <p class="mx-auto mt-1 max-w-sm text-xs psb-text-muted">Добавьте через кнопку «Блок» или каталог внизу.</p>
            </div>
        @else
            <ul
                class="m-0 list-none space-y-0 p-0"
                role="list"
                @if($sortableEnabled)
                    x-sortable
                    data-sortable-animation-duration="200"
                    x-on:end.stop="
                        (() => {
                            const y = window.scrollY;
                            const sortable = $event.target?.sortable;
                            const ids = sortable && typeof sortable.toArray === 'function' ? sortable.toArray() : null;
                            if (!ids) {
                                return;
                            }
                            const p = $wire.reorderSections(ids);
                            if (p && typeof p.then === 'function') {
                                p.then(() => {
                                    requestAnimationFrame(() => window.scrollTo(0, y));
                                }).catch(() => {});
                            } else {
                                requestAnimationFrame(() => window.scrollTo(0, y));
                            }
                        })()
                    "
                @endif
                wire:key="page-sections-sortable-{{ $record->getKey() }}"
            >
                @foreach($sections as $row)
                    @php
                        $s = $row['summary'];
                        $expanded = $this->isExpanded((int) $row['id']);
                        $channels = $s['channels'] ?? [];
                        $isHero = $row['type_id'] === 'hero';
                        $accent = match ($row['type_id']) {
                            'hero' => 'border-l-amber-500',
                            'structured_text', 'text_section' => 'border-l-sky-500',
                            'contacts_info' => 'border-l-emerald-500',
                            'content_faq' => 'border-l-violet-500',
                            'gallery' => 'border-l-fuchsia-500',
                            'rich_text' => 'border-l-sky-500',
                            default => 'border-l-gray-300 dark:border-l-gray-600',
                        };
                        $iconTone = match ($row['type_id']) {
                            'hero' => 'text-amber-500',
                            'structured_text', 'text_section', 'rich_text' => 'text-sky-500',
                            'contacts_info' => 'text-emerald-500',
                            'content_faq' => 'text-violet-500',
                            'gallery' => 'text-fuchsia-500',
                            default => 'psb-text-muted',
                        };
                        $cardClass = 'psb-card border-l-2 '.$accent.($isHero ? ' psb-card-hero' : '').($expanded ? ' psb-card-expanded' : '');
                        $warn = trim((string) ($s['warning'] ?? ''));
                        $siteHref = $publicUrl;
                        if ($publicUrl && $tenantThemeKey === 'moto' && in_array($row['type_id'], ['structured_text', 'text_section'], true)) {
                            $siteHref = $publicUrl.'#section-'.$row['id'];
                        }
                    @endphp
                    @if(!$loop->first)
                        <li class="relative flex min-h-[0.35rem] flex-col items-center justify-center py-0.5" wire:key="section-gap-{{ $row['id'] }}">
                            <div class="h-2 w-px opacity-60" style="background: var(--psb-border)"></div>
                            @if($sortableEnabled)
                                @php $prevSectionId = (int) $sections[$loop->index - 1]['id']; @endphp
                                <button
                                    type="button"
                                    wire:click="startAddBelow({{ $prevSectionId }})"
                                    class="psb-insert-below rounded-full border border-dashed px-2 py-0.5 text-[10px] font-medium opacity-0 transition-opacity duration-150 hover:opacity-100 focus:opacity-100 focus-visible:opacity-100"
                                    style="border-color: var(--psb-border); color: var(--psb-text-muted)"
                                >
                                    + здесь
                                </button>
                            @endif
                        </li>
                    @endif
                    <li
                        wire:key="section-card-{{ $row['id'] }}"
                        @if($sortableEnabled) x-sortable-item="{{ $row['id'] }}" @endif
                        class="{{ $cardClass }}"
                        @if($pageCtx->isHome)
                            data-setup-section-type="{{ $row['type_id'] }}"
                        @endif
                    >
                        <div class="flex flex-col gap-2 p-2.5 sm:flex-row sm:items-stretch sm:gap-3 sm:p-3 cursor-pointer transition hover:bg-black/[0.02] dark:hover:bg-white/[0.02]" wire:click="toggleExpanded({{ $row['id'] }})">
                            <div class="flex flex-shrink-0 items-start gap-2 sm:w-[3.5rem] sm:flex-col sm:items-center sm:pt-0.5">
                                @if($sortableEnabled)
                                    <button
                                        type="button"
                                        x-sortable-handle
                                        class="psb-drag-handle cursor-grab touch-manipulation rounded-md p-1.5 psb-text-muted transition active:cursor-grabbing"
                                        title="Перетащить"
                                        wire:click.stop
                                    >
                                        <x-filament::icon icon="heroicon-o-bars-3" class="h-4 w-4" />
                                    </button>
                                @else
                                    <span class="inline-block w-7 sm:h-7" aria-hidden="true"></span>
                                @endif
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg" style="background: var(--psb-bg-elevated); border: 1px solid var(--psb-border)">
                                    <x-filament::icon :icon="$row['icon']" class="h-4 w-4 {{ $iconTone }}" />
                                </span>
                                <span class="hidden text-center text-[10px] font-semibold tabular-nums psb-text-muted sm:block">#{{ $row['position'] }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0 text-[10px] font-medium uppercase tracking-wide psb-text-muted">
                                            <span>{{ $row['type_label_ui'] ?? $row['type_label'] }}</span>
                                            <span class="font-normal opacity-60">#{{ $row['position'] }}</span>
                                            @if($warn !== '')
                                                <span class="text-amber-600 dark:text-amber-400" title="{{ $warn }}">!</span>
                                            @endif
                                        </p>
                                        <p class="mt-0.5 text-sm font-semibold leading-snug psb-text-primary sm:text-base">{{ $row['card_title'] }}</p>
                                        @if(!empty($channels))
                                            <div class="mt-1 flex flex-wrap gap-1.5" role="presentation">
                                                @foreach($channels as $ch)
                                                    <span
                                                        class="{{ ($ch['on'] ?? false) ? 'text-primary-600 dark:text-primary-400' : 'psb-text-muted opacity-25' }}"
                                                        title="{{ $ch['label'] }}"
                                                    >
                                                        <x-filament::icon :icon="$ch['icon']" class="h-3.5 w-3.5" />
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(($row['card_preview'] ?? '') !== '')
                                            <p class="mt-1 text-xs leading-snug psb-text-secondary line-clamp-2">{{ $row['card_preview'] }}</p>
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click.stop="toggleExpanded({{ $row['id'] }})"
                                        class="flex-shrink-0 rounded-md p-1 psb-text-muted hover:bg-black/5 dark:hover:bg-white/10"
                                        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                                        title="{{ $expanded ? 'Свернуть' : 'Подробнее' }}"
                                    >
                                        <x-filament::icon icon="heroicon-o-chevron-down" class="h-5 w-5 transition-transform {{ $expanded ? 'rotate-180' : '' }}" />
                                    </button>
                                </div>
                                <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] psb-text-muted">
                                    <span>{{ $statusShort($row['status']) }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $row['is_visible'] ? 'Виден' : 'Скрыт' }}</span>
                                </p>
                            </div>

                            <div class="flex flex-shrink-0 flex-col justify-center gap-1.5 border-t pt-3 sm:w-[8rem] sm:border-t-0 sm:pt-0 sm:pl-3" style="border-color: var(--psb-border)" wire:click.stop>
                                <button
                                    type="button"
                                    wire:click.stop="startEdit({{ $row['id'] }})"
                                    @if($pageCtx->isHome && $isHero)
                                        data-setup-focus-target=""
                                    @endif
                                    class="fi-btn fi-color-custom fi-btn-color-primary inline-flex min-h-8 w-full items-center justify-center rounded-lg px-3 py-1.5 text-[11.5px] font-semibold text-white shadow-sm transition"
                                    style="background-color: var(--primary-600); --fi-color: var(--primary-600)"
                                >
                                    Редактировать
                                </button>
                                <div class="flex items-center justify-center gap-0.5 mt-0.5">
                                    <button
                                        type="button"
                                        wire:click="toggleVisibility({{ $row['id'] }})"
                                        class="flex h-7 flex-1 flex-shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                                        title="{{ $row['is_visible'] ? 'Скрыть' : 'Показать' }}"
                                        aria-label="{{ $row['is_visible'] ? 'Скрыть на сайте' : 'Показать на сайте' }}"
                                    >
                                        <x-filament::icon :icon="$row['is_visible'] ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'" class="h-4 w-4" />
                                    </button>
                                    <div
                                        class="psb-menu-anchor flex-1 flex-shrink-0"
                                        x-data="{ open: false }"
                                        @keydown.escape.window="open = false"
                                        @click.outside="open = false"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full h-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                                            @click.prevent="open = !open"
                                            :aria-expanded="open"
                                            aria-haspopup="true"
                                            aria-label="Ещё"
                                        >
                                            <x-filament::icon icon="heroicon-o-ellipsis-vertical" class="h-4 w-4" />
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition.opacity.duration.100ms
                                            class="psb-menu-panel"
                                            style="display: none;"
                                        >
                                            @if($publicUrl)
                                                <a href="{{ $siteHref }}" target="_blank" rel="noopener noreferrer" class="block w-full px-3 py-2 text-left text-sm text-inherit no-underline hover:bg-[var(--psb-menu-hover)]" @click="open = false">На сайте ↗</a>
                                            @endif
                                            <p class="psb-menu-group-label">Порядок</p>
                                            <button type="button" @click="open = false" wire:click="startAddBelow({{ $row['id'] }})">Блок ниже</button>
                                            <button type="button" @click="open = false" wire:click="moveUp({{ $row['id'] }})">Выше</button>
                                            <button type="button" @click="open = false" wire:click="moveDown({{ $row['id'] }})">Ниже</button>
                                            <p class="psb-menu-group-label">Копия</p>
                                            <button type="button" @click="open = false" wire:click="duplicate({{ $row['id'] }})">Дублировать</button>
                                            <div class="psb-menu-sep" aria-hidden="true"></div>
                                            <button type="button" @click="open = false" wire:click="openDeleteModal({{ $row['id'] }})" class="psb-menu-item-danger">Удалить…</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($expanded)
                            <div class="border-t sm:px-1" style="border-color: var(--psb-border)">
                                <div class="px-2.5 py-4 sm:px-3 space-y-4">
                                    <!-- 1. PREVIEW ZONE -->
                                    <div class="psb-expanded-preview border-l-2 rounded-r-lg" style="background-color: var(--psb-bg-page); border-top-color: var(--psb-border); border-right-color: var(--psb-border); border-bottom-color: var(--psb-border); border-left-color: rgba(100, 116, 139, 0.4);">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-[10px] font-bold uppercase tracking-wider psb-text-muted">Отображается на сайте</p>
                                            @if(($row['card_site_tooltip'] ?? '') !== '')
                                                <div class="group relative flex items-center">
                                                    <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 psb-text-muted cursor-help" />
                                                    <div class="pointer-events-none absolute bottom-full right-0 mb-2 w-56 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-gray-800 bg-white shadow-lg border rounded-lg p-2.5 text-[11px] leading-relaxed psb-text-secondary z-10" style="border-color: var(--psb-border)">
                                                        {{ $row['card_site_tooltip'] }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        @if(!empty($channels))
                                            <div class="mb-2 flex flex-wrap gap-2">
                                                @foreach($channels as $ch)
                                                    <span class="{{ ($ch['on'] ?? false) ? 'psb-text-secondary' : 'psb-text-muted opacity-35' }}" title="{{ $ch['label'] }}">
                                                        <x-filament::icon :icon="$ch['icon']" class="h-4 w-4" />
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        <p class="text-[15px] font-bold psb-text-primary tracking-tight">{{ $row['card_title'] }}</p>
                                        @if(($row['card_preview'] ?? '') !== '')
                                            <p class="mt-1.5 text-sm psb-text-secondary leading-snug">{{ $row['card_preview'] }}</p>
                                        @endif
                                        @if($warn !== '')
                                            <p class="mt-2 text-xs text-amber-800 dark:text-amber-300 font-medium">{{ $warn }}</p>
                                        @endif
                                    </div>

                                    <!-- 2. QUICK EDIT ZONE -->
                                    <div class="psb-quick-zone border-t border-dashed pt-4 border-gray-200 dark:border-gray-800">
                                        <div class="mb-3 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider psb-text-muted">
                                            <x-filament::icon icon="heroicon-s-adjustments-horizontal" class="h-4 w-4 psb-text-secondary" />
                                            Настройки списка
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="mb-1.5 block text-[11px] font-medium psb-text-secondary" for="sec-title-{{ $row['id'] }}">Подпись для навигации</label>
                                                <input
                                                    id="sec-title-{{ $row['id'] }}"
                                                    type="text"
                                                    value="{{ $row['title'] }}"
                                                    placeholder="Подпись"
                                                    x-on:blur="$wire.patchSectionMeta({{ $row['id'] }}, { title: $event.target.value })"
                                                    class="psb-input-field"
                                                />
                                            </div>
                                            @if($row['has_block_title_quick'])
                                                <div>
                                                    <label class="mb-1.5 block text-[11px] font-medium psb-text-secondary" for="sec-block-title-{{ $row['id'] }}">Заголовок в самом блоке</label>
                                                    <input
                                                        id="sec-block-title-{{ $row['id'] }}"
                                                        type="text"
                                                        value="{{ $row['block_title_quick_value'] }}"
                                                        placeholder="Заголовок"
                                                        x-on:blur="$wire.patchSectionMeta({{ $row['id'] }}, { block_title: $event.target.value })"
                                                        class="psb-input-field"
                                                    />
                                                </div>
                                            @endif
                                            <div>
                                                <label class="mb-1.5 block text-[11px] font-medium psb-text-secondary" for="sec-status-{{ $row['id'] }}">Статус видимости</label>
                                                <select
                                                    id="sec-status-{{ $row['id'] }}"
                                                    class="psb-input-field psb-select-field"
                                                    wire:change="patchSectionMeta({{ $row['id'] }}, { status: $event.target.value })"
                                                >
                                                    @foreach(PageSection::statuses() as $statusKey => $statusLabel)
                                                        <option value="{{ $statusKey }}" {{ $row['status'] === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if($sectionCount > 0)
            <div class="mt-3">
                <button
                    type="button"
                    wire:click="startAddToEnd"
                    @if(($record->slug ?? null) === 'home')
                        data-setup-action="pages.home.add_block"
                    @endif
                    class="fi-btn fi-btn-color-primary inline-flex min-h-9 items-center justify-center rounded-lg px-4 py-2 text-xs font-semibold text-white"
                >
                    Блок в конец
                </button>
            </div>
        @endif
    </div>

    @if($catalog->isNotEmpty())
        <p class="psb-outline-label mb-2 mt-8 text-[10px] font-semibold uppercase tracking-wider psb-text-muted">Каталог</p>
        <div id="page-section-catalog" class="psb-catalog-wrap mt-1 p-3 sm:p-4" x-data="{ q: '' }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold psb-text-primary sm:text-base">Добавить</h4>
                    @if($insertAfterId !== null)
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-[11px] psb-text-muted">
                            <span>После: <strong class="psb-text-secondary">{{ $insertAfterLabel ?: '#'.$insertAfterId }}</strong></span>
                            <button type="button" wire:click="clearInsertAfter" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Сброс</button>
                        </p>
                    @endif
                </div>
                <div class="w-full sm:max-w-[14rem]">
                    <input
                        id="page-section-catalog-q-{{ $this->getId() }}"
                        type="search"
                        x-model="q"
                        placeholder="Поиск…"
                        class="psb-search w-full py-1.5 text-sm"
                    />
                </div>
            </div>

            <div class="mt-4 space-y-4">
                @foreach($catalog as $group)
                    @php
                        $groupItemBlobsLower = collect($group['items'])->map(
                            fn (array $item): string => mb_strtolower($item['label'].' '.$item['description'].' '.$group['label'])
                        )->values()->all();
                    @endphp
                    <div x-show="(() => { const qq = q.trim().toLowerCase(); if (!qq) return true; const cat = @js(mb_strtolower($group['label'])); if (cat.includes(qq)) return true; const blobs = @js($groupItemBlobsLower); return blobs.some((b) => b.includes(qq)); })()">
                        <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider psb-text-muted">{{ $group['label'] }}</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($group['items'] as $item)
                                <button
                                    type="button"
                                    wire:click="startAdd('{{ $item['id'] }}', {{ $insertAfterId === null ? 'null' : $insertAfterId }})"
                                    x-show="(() => { const qq = q.trim().toLowerCase(); if (!qq) return true; const blob = @js(mb_strtolower($item['label'].' '.$item['description'].' '.$group['label'])); return blob.includes(qq); })()"
                                    @if(($record->slug ?? null) === 'home')
                                        data-setup-section-type="{{ $item['id'] }}"
                                        data-setup-action="pages.home.add_section"
                                    @endif
                                    class="psb-catalog-tile group flex min-h-0 w-full items-center gap-2.5 p-2.5 text-left sm:gap-3 sm:p-3"
                                >
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-primary-600/10 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                                        <x-filament::icon :icon="$item['icon']" class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs font-semibold leading-tight psb-text-primary sm:text-sm">{{ $item['label'] }}</span>
                                        @if(($item['description'] ?? '') !== '')
                                            <span class="mt-0.5 block text-[11px] leading-snug psb-text-muted line-clamp-2">{{ $item['description'] }}</span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($showDeleteModal && $deleteRow)
        @teleport('body')
            <div
                class="page-sections-builder-teleport fixed inset-0 z-[290] flex items-end justify-center bg-black/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                wire:key="delete-section-modal"
                wire:click="closeDeleteModal"
            >
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:p-6" wire:click.stop>
                    <h4 class="text-lg font-semibold text-gray-950 dark:text-white">Удалить блок?</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Тип: <strong>{{ $deleteRow['type_label_ui'] ?? $deleteRow['type_label'] }}</strong></p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Подпись: <strong>{{ $deleteRow['summary']['displayTitle'] }}</strong></p>
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">
                        Блок исчезнет с сайта. Восстановить нельзя.
                    </p>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <button type="button" wire:click="closeDeleteModal" class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-lg px-4 py-2 text-sm font-semibold">Отмена</button>
                        <button type="button" wire:click="confirmDelete" class="fi-btn fi-btn-color-danger rounded-lg px-4 py-2 text-sm font-semibold text-white">Удалить</button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @if($showEditor)
        @teleport('body')
            <div
                class="page-sections-builder-editor page-sections-builder-teleport fixed inset-0 z-[300]"
                role="dialog"
                aria-modal="true"
                wire:key="page-section-editor-root-{{ $editingSectionId ?? 'new' }}-{{ $activeTypeId ?? 'x' }}"
                data-setup-editor-section-id="{{ $editingSectionId ?? '' }}"
                data-psb-livewire-id="{{ $this->getId() }}"
            >
                <div
                    class="absolute inset-0 bg-black/40 dark:bg-black/60"
                    wire:click="closeEditor"
                ></div>
                <aside
                    class="absolute inset-y-0 right-0 z-[1] flex w-full max-w-3xl flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-gray-900 lg:max-w-4xl"
                    wire:click.stop
                >
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $record->name }}</p>
                            <h4 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $editingSectionId ? 'Редактирование блока' : 'Новый блок на странице' }}
                            </h4>
                        </div>
                        <button type="button" wire:click="closeEditor" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                            <span class="sr-only">Закрыть</span>
                            <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                        <div class="space-y-4">
                            {{ $this->sectionEditor }}
                        </div>
                    </div>
                    <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" wire:click="closeEditor" class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-lg px-4 py-2 text-sm font-semibold">Отмена</button>
                            <button type="button" wire:click="save" class="fi-btn fi-btn-color-primary rounded-lg px-4 py-2 text-sm font-semibold text-white">Сохранить</button>
                        </div>
                    </div>
                </aside>
                @include('livewire.tenant.partials.tenant-public-file-picker-overlay', ['mount' => 'nested'])
            </div>
        @endteleport
    @endif

    @include('livewire.tenant.partials.tenant-public-file-picker-inputs', ['uploadSlotAttribute' => 'data-tenant-public-upload-input'])
    @if (! $showEditor)
        @include('livewire.tenant.partials.tenant-public-file-picker-overlay', ['mount' => 'teleport'])
    @endif

    <x-filament-actions::modals />
</div>
