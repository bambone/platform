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
@endphp

<div class="page-sections-builder-root psb-root fi-section p-4 sm:p-6">
    <header class="psb-header sticky top-0 z-10 mb-4 pb-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold tracking-tight psb-text-primary">{{ $record->name }}</h3>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs psb-text-secondary">
                    <span>Структура страницы</span>
                    @if($record->slug)
                        <span class="psb-text-muted">·</span>
                        <code class="font-mono text-[11px] psb-text-secondary">{{ $record->slug }}</code>
                    @endif
                    <span class="psb-text-muted">·</span>
                    <span>{{ $metrics['total'] }} блоков</span>
                    <span>{{ $metrics['status_published'] }} опубл.</span>
                    @if($metrics['hidden_on_site'] > 0)
                        <span>{{ $metrics['hidden_on_site'] }} скрыто</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                @if($publicUrl)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener noreferrer" class="psb-btn-edit !w-auto px-3">
                        На сайт
                    </a>
                @endif
                <button type="button" wire:click="startAddToEnd" class="fi-btn fi-btn-color-primary inline-flex min-h-9 items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-white">
                    Добавить блок
                </button>
                <a href="{{ $contentTabUrl }}" wire:navigate class="psb-btn-edit !w-auto px-3">
                    Настройки
                </a>
            </div>
        </div>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <input
                    id="page-section-search-{{ $this->getId() }}"
                    type="search"
                    wire:model.live.debounce.300ms="sectionSearch"
                    placeholder="Найти блок…"
                    aria-label="Поиск по блокам"
                    class="psb-search block w-full"
                />
            </div>
            <label class="inline-flex cursor-pointer items-center gap-2 text-xs psb-text-secondary">
                <input type="checkbox" wire:model.live="showOnlyHidden" class="rounded border-gray-300 text-primary-600 dark:border-white/20" />
                Только скрытые
            </label>
            <select wire:model.live="listDensity" class="psb-search w-auto py-1.5 text-xs" aria-label="Плотность списка">
                <option value="comfort">Комфорт</option>
                <option value="compact">Компакт</option>
            </select>
            <span class="hidden h-4 w-px sm:inline-block" style="background: var(--psb-border)"></span>
            <button type="button" wire:click="expandAllSections" class="text-xs font-medium psb-text-secondary hover:underline">Развернуть всё</button>
            <button type="button" wire:click="collapseAllSections" class="text-xs font-medium psb-text-secondary hover:underline">Свернуть</button>
        </div>
        @if(!$sortableEnabled)
            <p class="mt-2 text-[11px] psb-text-secondary">Перетаскивание доступно без поиска и фильтра «только скрытые».</p>
        @endif
    </header>

    <div class="space-y-0" role="region" aria-label="Структура страницы на сайте">

        <div class="psb-main-card p-4 sm:p-5">
            <div class="flex flex-wrap items-start gap-4">
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg psb-text-secondary" style="background: var(--psb-bg-elevated)">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-6 w-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-base font-semibold psb-text-primary">Основной контент страницы</h4>
                    <p class="mt-0.5 text-xs psb-text-muted">Системный блок · редактируется отдельно</p>
                    @if($mainCard['mode'] === 'home')
                        <p class="mt-2 text-sm leading-relaxed psb-text-secondary">
                            На главной основной текст не задаётся здесь — только блоки ниже. SEO и страница — в настройках.
                        </p>
                    @else
                        @if($mainCard['excerpt'] !== '')
                            <p class="mt-2 text-sm leading-relaxed psb-text-secondary line-clamp-3">{{ $mainCard['excerpt'] }}</p>
                        @else
                            <p class="mt-2 text-sm psb-text-secondary">Текст пока пустой или очень короткий.</p>
                        @endif
                    @endif
                    <a href="{{ $mainCard['edit_url'] }}" wire:navigate class="psb-btn-edit mt-3 !inline-flex !w-auto">
                        Редактировать основной текст
                    </a>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-2" aria-hidden="true">
            <div class="h-4 w-px psb-text-muted" style="background: var(--psb-border)"></div>
        </div>

        <div class="mb-2 mt-1">
            <h4 class="text-[11px] font-semibold uppercase tracking-wide psb-text-muted">Дополнительные блоки</h4>
        </div>

        @if($sectionCount === 0)
            <div class="psb-empty p-6 text-center sm:p-8">
                <p class="text-sm font-medium psb-text-primary">Нет дополнительных секций</p>
                <p class="mx-auto mt-2 max-w-md text-sm psb-text-secondary">Выберите тип в «Добавить блок» ниже или нажмите «Добавить блок в конец».</p>
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
                            $wire.reorderSections($event.target.sortable.toArray()).then(() => {
                                requestAnimationFrame(() => window.scrollTo(0, y));
                            });
                        })()
                    "
                @endif
                wire:key="page-sections-sortable-{{ $record->getKey() }}"
            >
                @foreach($sections as $row)
                    @php
                        $s = $row['summary'];
                        $expanded = $this->isExpanded((int) $row['id']);
                        $ph = $s['primaryHeadline'] ?? null;
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
                    @endphp
                    @if(!$loop->first)
                        <li class="flex flex-col items-center gap-1 py-1.5" wire:key="section-gap-{{ $row['id'] }}">
                            <div class="h-3 w-px" style="background: var(--psb-border)"></div>
                            @if($sortableEnabled)
                                @php $prevSectionId = (int) $sections[$loop->index - 1]['id']; @endphp
                                <button
                                    type="button"
                                    wire:click="startAddBelow({{ $prevSectionId }})"
                                    class="rounded-full border border-dashed px-2.5 py-0.5 text-[11px] font-medium psb-text-secondary hover:opacity-80"
                                    style="border-color: var(--psb-border)"
                                >
                                    + Блок ниже
                                </button>
                            @endif
                        </li>
                    @endif
                    <li
                        wire:key="section-card-{{ $row['id'] }}"
                        @if($sortableEnabled) x-sortable-item="{{ $row['id'] }}" @endif
                        class="{{ $cardClass }}"
                    >
                        <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-stretch sm:gap-4 sm:p-4">
                            {{-- Left: drag, icon, index --}}
                            <div class="flex flex-shrink-0 items-start gap-2 sm:w-[4.25rem] sm:flex-col sm:items-center sm:pt-0.5">
                                @if($sortableEnabled)
                                    <button
                                        type="button"
                                        x-sortable-handle
                                        class="cursor-grab touch-manipulation rounded-lg p-1.5 psb-text-muted transition active:cursor-grabbing"
                                        style="background: var(--psb-bg-elevated); border: 1px solid var(--psb-border)"
                                        title="Перетащить"
                                    >
                                        <x-filament::icon icon="heroicon-o-bars-3" class="h-5 w-5" />
                                    </button>
                                @else
                                    <span class="inline-block w-9 sm:h-9" aria-hidden="true"></span>
                                @endif
                                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg" style="background: var(--psb-bg-elevated); border: 1px solid var(--psb-border)">
                                    <x-filament::icon :icon="$row['icon']" class="h-5 w-5 {{ $iconTone }}" />
                                </span>
                                <span class="hidden text-center text-[11px] font-medium tabular-nums psb-text-muted sm:block">#{{ $row['position'] }}</span>
                            </div>
                            {{-- Center: контентный preview (клик = expand) --}}
                            <button
                                type="button"
                                wire:click="toggleExpanded({{ $row['id'] }})"
                                class="psb-preview-hit min-w-0 flex-1 rounded-lg text-left sm:-m-1 sm:p-2"
                                aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                            >
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] psb-text-muted">
                                    <span class="font-medium uppercase tracking-wide">{{ $row['type_label'] }}</span>
                                    <span>·</span>
                                    <span>{{ PageSection::statuses()[$row['status']] ?? $row['status'] }}</span>
                                    <span>·</span>
                                    <span>{{ $row['is_visible'] ? 'На сайте' : 'Скрыт' }}</span>
                                    @if(!empty($s['warning']))
                                        <span class="text-amber-700 dark:text-amber-400">· внимание</span>
                                    @endif
                                </div>
                                @if(!empty($channels))
                                    <div class="mt-2 flex flex-wrap gap-2" role="presentation">
                                        @foreach($channels as $ch)
                                            <span
                                                class="{{ ($ch['on'] ?? false) ? 'psb-text-secondary opacity-100' : 'psb-text-muted opacity-35' }}"
                                                title="{{ $ch['label'] }} — {{ ($ch['on'] ?? false) ? 'есть' : 'нет' }}"
                                            >
                                                <x-filament::icon :icon="$ch['icon']" class="h-4 w-4" />
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                @if($ph)
                                    <p class="mt-2 text-base font-semibold leading-snug psb-text-primary">{{ $ph }}</p>
                                    @if($s['displayTitle'] !== $ph)
                                        <p class="mt-0.5 text-[11px] psb-text-muted">Список: {{ $s['displayTitle'] }}</p>
                                    @endif
                                @else
                                    <p class="mt-2 text-base font-semibold leading-snug psb-text-primary">{{ $s['displayTitle'] }}</p>
                                @endif
                                @php $badgeSlice = array_slice($s['badges'] ?? [], 0, 2); @endphp
                                @if($badgeSlice !== [])
                                    <p class="mt-1.5 text-[11px] psb-text-muted">{{ implode(' · ', $badgeSlice) }}</p>
                                @endif
                                @if(!empty($s['summaryLines']))
                                    <p class="mt-1.5 text-sm leading-relaxed psb-text-secondary line-clamp-2">{{ implode(' ', $s['summaryLines']) }}</p>
                                @endif
                                @if(!empty($s['warning']))
                                    <p class="mt-1.5 text-xs text-amber-800 dark:text-amber-300">{{ $s['warning'] }}</p>
                                @endif
                                <p class="mt-2 text-[10px] psb-text-muted sm:hidden">#{{ $row['position'] }} · {{ $expanded ? 'свернуть' : 'развернуть' }}</p>
                            </button>
                            {{-- Right: главное действие + видимость + меню --}}
                            <div class="flex flex-shrink-0 flex-col items-stretch justify-center gap-1.5 border-t pt-3 sm:min-w-[9rem] sm:w-[9rem] sm:border-t-0 sm:pt-0 sm:pl-1" style="border-color: var(--psb-border)" wire:click.stop>
                                <button type="button" wire:click="startEdit({{ $row['id'] }})" class="psb-btn-edit">
                                    Редактировать
                                </button>
                                <button
                                    type="button"
                                    wire:click="toggleVisibility({{ $row['id'] }})"
                                    class="psb-btn-ghost"
                                    title="{{ $row['is_visible'] ? 'Скрыть на сайте' : 'Показать на сайте' }}"
                                    aria-label="{{ $row['is_visible'] ? 'Скрыть на сайте' : 'Показать на сайте' }}"
                                >
                                    <x-filament::icon :icon="$row['is_visible'] ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'" class="h-5 w-5" />
                                </button>
                                <div
                                    class="psb-menu-anchor"
                                    x-data="{ open: false }"
                                    @keydown.escape.window="open = false"
                                    @click.outside="open = false"
                                >
                                    <button
                                        type="button"
                                        class="psb-menu-trigger"
                                        @click.prevent="open = !open"
                                        :aria-expanded="open"
                                        aria-haspopup="true"
                                    >
                                        <x-filament::icon icon="heroicon-o-ellipsis-vertical" class="h-5 w-5" />
                                    </button>
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition.opacity.duration.100ms
                                        class="psb-menu-panel"
                                        style="display: none;"
                                    >
                                        <button type="button" @click="open = false" wire:click="toggleExpanded({{ $row['id'] }})">{{ $expanded ? 'Свернуть' : 'Развернуть' }}</button>
                                        <button type="button" @click="open = false" wire:click="startAddBelow({{ $row['id'] }})">Добавить ниже</button>
                                        <button type="button" @click="open = false" wire:click="moveUp({{ $row['id'] }})">Выше</button>
                                        <button type="button" @click="open = false" wire:click="moveDown({{ $row['id'] }})">Ниже</button>
                                        <button type="button" @click="open = false" wire:click="duplicate({{ $row['id'] }})">Дублировать</button>
                                        <div class="psb-menu-sep" aria-hidden="true"></div>
                                        <button type="button" @click="open = false" wire:click="openDeleteModal({{ $row['id'] }})" class="psb-menu-item-danger">Удалить…</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($expanded)
                            <div class="border-t px-3 py-4 sm:px-4" style="border-color: var(--psb-border)">
                                <div class="psb-expanded-preview">
                                    @if(!empty($channels))
                                        <div class="mb-2 flex flex-wrap gap-2">
                                            @foreach($channels as $ch)
                                                <span class="{{ ($ch['on'] ?? false) ? 'psb-text-secondary' : 'psb-text-muted opacity-35' }}" title="{{ $ch['label'] }}">
                                                    <x-filament::icon :icon="$ch['icon']" class="h-4 w-4" />
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($ph)
                                        <p class="text-base font-semibold psb-text-primary">{{ $ph }}</p>
                                    @else
                                        <p class="text-base font-semibold psb-text-primary">{{ $s['displayTitle'] }}</p>
                                    @endif
                                    @if(!empty($s['summaryLines']))
                                        <p class="mt-2 text-sm psb-text-secondary line-clamp-3">{{ implode(' ', $s['summaryLines']) }}</p>
                                    @endif
                                    @php $expBadges = array_slice($s['badges'] ?? [], 0, 3); @endphp
                                    @if($expBadges !== [])
                                        <p class="mt-2 text-[11px] psb-text-muted">{{ implode(' · ', $expBadges) }}</p>
                                    @endif
                                </div>
                                <div class="psb-quick-zone">
                                    <p class="text-[11px] font-medium psb-text-muted">Быстрые правки</p>
                                    <div class="mt-2 space-y-2">
                                        <input
                                            id="sec-title-{{ $row['id'] }}"
                                            type="text"
                                            value="{{ $row['title'] }}"
                                            placeholder="Подпись в списке"
                                            x-on:blur="$wire.patchSectionMeta({{ $row['id'] }}, { title: $event.target.value })"
                                            class="psb-input-minimal"
                                        />
                                        @if($row['has_block_title_quick'])
                                            <input
                                                id="sec-block-title-{{ $row['id'] }}"
                                                type="text"
                                                value="{{ $row['block_title_quick_value'] }}"
                                                placeholder="{{ in_array($row['type_id'], ['hero', 'gallery', 'rich_text'], true) ? 'Заголовок на сайте' : 'Заголовок в блоке' }}"
                                                x-on:blur="$wire.patchSectionMeta({{ $row['id'] }}, { block_title: $event.target.value })"
                                                class="psb-input-minimal"
                                            />
                                        @endif
                                        <select
                                            id="sec-status-{{ $row['id'] }}"
                                            class="psb-select"
                                            wire:change="patchSectionMeta({{ $row['id'] }}, { status: $event.target.value })"
                                        >
                                            @foreach(PageSection::statuses() as $statusKey => $statusLabel)
                                                <option value="{{ $statusKey }}" {{ $row['status'] === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <button type="button" wire:click="startEdit({{ $row['id'] }})" class="fi-btn fi-btn-color-primary fi-btn-size-sm inline-flex items-center rounded-lg px-4 py-2 text-xs font-semibold text-white">
                                        Полный редактор
                                    </button>
                                    <button type="button" wire:click="startAddBelow({{ $row['id'] }})" class="text-xs font-medium psb-text-secondary hover:underline">
                                        + блок ниже
                                    </button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if($sectionCount > 0)
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="startAddToEnd" class="fi-btn fi-btn-color-primary inline-flex min-h-11 items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white">
                    Добавить блок в конец
                </button>
            </div>
        @endif
    </div>

    @if($catalog->isNotEmpty())
        <div id="page-section-catalog" class="psb-catalog-wrap mt-10 p-5 sm:p-6" x-data="{ q: '' }">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h4 class="text-lg font-semibold tracking-tight psb-text-primary">Добавить блок</h4>
                    <p class="mt-1 max-w-xl text-sm psb-text-secondary">Выберите тип — блок появится на странице, порядок можно изменить перетаскиванием.</p>
                    @if($insertAfterId !== null)
                        <p class="mt-2 text-xs psb-text-secondary">
                            Вставка после:
                            @if($insertAfterLabel)
                                <span class="font-medium psb-text-primary">«{{ $insertAfterLabel }}»</span>
                            @else
                                <span>#{{ $insertAfterId }}</span>
                            @endif
                        </p>
                    @endif
                </div>
                <div class="w-full lg:max-w-sm">
                    <label class="mb-1 block text-xs psb-text-muted" for="page-section-catalog-q-{{ $this->getId() }}">Поиск</label>
                    <input
                        id="page-section-catalog-q-{{ $this->getId() }}"
                        type="search"
                        x-model="q"
                        placeholder="Название или описание…"
                        class="psb-search w-full"
                    />
                </div>
            </div>

            <div class="mt-6 space-y-6">
                @foreach($catalog as $group)
                    <div x-show="(() => { const qq = q.trim().toLowerCase(); if (!qq) return true; const cat = @js(mb_strtolower($group['label'])); if (cat.includes(qq)) return true; return false; })()">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider psb-text-muted">{{ $group['label'] }}</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($group['items'] as $item)
                                <button
                                    type="button"
                                    wire:click="startAdd('{{ $item['id'] }}', {{ $insertAfterId === null ? 'null' : $insertAfterId }})"
                                    x-show="(() => { const qq = q.trim().toLowerCase(); if (!qq) return true; const blob = @js(mb_strtolower($item['label'].' '.$item['description'].' '.$group['label'])); return blob.includes(qq); })()"
                                    class="psb-catalog-tile group flex min-h-[4.75rem] w-full items-start gap-3 p-4 text-left"
                                >
                                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-primary-600/10 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                                        <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold psb-text-primary">{{ $item['label'] }}</span>
                                        <span class="mt-0.5 block text-xs leading-snug psb-text-secondary">{{ $item['description'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- @teleport требует ровно один корневой элемент: пустой шаблон ломает Alpine (_x_teleportBack на null). --}}
    @if($showDeleteModal && $deleteRow)
        @teleport('body')
            <div
                class="fixed inset-0 z-[290] flex items-end justify-center bg-black/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                wire:key="delete-section-modal"
                wire:click="closeDeleteModal"
            >
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:p-6" wire:click.stop>
                    <h4 class="text-lg font-semibold text-gray-950 dark:text-white">Удалить блок с сайта?</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Тип блока: <strong>{{ $deleteRow['type_label'] }}</strong></p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Подпись в редакторе: <strong>{{ $deleteRow['summary']['displayTitle'] }}</strong></p>
                    @if(!empty($deleteRow['summary']['summaryLines']))
                        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">Содержимое (кратко):</p>
                        <ul class="mt-1 list-inside list-disc text-sm text-gray-600 dark:text-gray-400">
                            @foreach(array_slice($deleteRow['summary']['summaryLines'], 0, 3) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">
                        Блок <strong>пропадёт со страницы на сайте</strong>. Восстановить тот же блок нельзя — только создать новый.
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
                class="page-sections-builder-editor fixed inset-0 z-[300]"
                role="dialog"
                aria-modal="true"
                wire:key="page-section-editor-root-{{ $editingSectionId ?? 'new' }}-{{ $activeTypeId ?? 'x' }}"
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
                        <h4 class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $editingSectionId ? 'Редактирование блока' : 'Новый блок' }}
                        </h4>
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
            </div>
        @endteleport
    @endif

    @include('livewire.tenant.partials.tenant-public-file-picker-modal', ['uploadSlotAttribute' => 'data-tenant-public-upload-input'])

    <x-filament-actions::modals />
</div>
