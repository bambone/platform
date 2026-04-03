@php
    $sections = $this->currentSections;
    $sectionCount = count($sections);
    $catalog = $this->availableSectionCatalog;
    $scrollCatalog = "document.getElementById('page-section-catalog')?.scrollIntoView({behavior:'smooth', block:'start'})";
@endphp

<div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
    {{-- Контекст страницы --}}
    <header class="mb-6 border-b border-gray-100 pb-5 dark:border-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Секции страницы</h3>
        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
            <span class="text-gray-500 dark:text-gray-400">Страница:</span>
            <span class="font-medium text-gray-950 dark:text-white">{{ $record->name }}</span>
            @if($record->slug)
                <span class="text-gray-400 dark:text-gray-500">·</span>
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $record->slug }}</code>
            @endif
        </p>
        @if($record->slug !== 'home')
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                Здесь вы собираете <strong class="font-medium text-gray-800 dark:text-gray-200">дополнительные блоки</strong> именно для этой страницы. Порядок сверху вниз совпадает с порядком на сайте (ниже основного текста).
            </p>
        @else
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                Порядок блоков на главной совпадает со списком ниже (сверху вниз), включая блок <strong class="font-medium text-gray-800 dark:text-gray-200">«Каталог мотоциклов»</strong> — его можно перемещать как остальные секции.
            </p>
        @endif
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Секция <code class="rounded bg-gray-100 px-1 dark:bg-white/10">main</code> в этот список не входит — её текст задаётся на вкладке «Контент и настройки».
        </p>
    </header>

    {{-- Primary: вертикальный поток страницы --}}
    <div class="space-y-0" role="region" aria-label="Структура страницы на сайте">

        {{-- Плашка основного контента (не из БД) --}}
        <div class="rounded-xl border-2 border-dashed border-primary-300/80 bg-primary-50/40 p-4 dark:border-primary-500/40 dark:bg-primary-950/20 sm:p-5">
            <div class="flex flex-wrap items-start gap-3">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary-100 text-sm font-bold text-primary-800 dark:bg-primary-500/20 dark:text-primary-200" aria-hidden="true">M</span>
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Основной контент страницы</h4>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Редактируется на вкладке <strong class="font-medium text-gray-800 dark:text-gray-200">«Контент и настройки»</strong> (основной текст / HTML). Здесь настраиваются только блоки, которые выводятся <em>после</em> него.
                    </p>
                </div>
            </div>
        </div>

        {{-- Связка вниз --}}
        <div class="flex flex-col items-center py-1 sm:py-2" aria-hidden="true">
            <div class="h-5 w-px bg-gray-300 dark:bg-white/20"></div>
            <span class="leading-none text-gray-400 dark:text-gray-500" aria-hidden="true">↓</span>
        </div>

        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Дополнительные блоки на странице</h4>
            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">в списке: {{ $sectionCount }}</span>
        </div>

        @if($sectionCount === 0)
            {{-- Empty state --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-6 text-center dark:border-white/10 dark:bg-white/5 sm:p-8">
                <p class="text-sm font-medium text-gray-900 dark:text-white">У этой страницы пока нет дополнительных секций</p>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400">
                    Сейчас на сайте отображается только <strong class="font-medium text-gray-800 dark:text-gray-200">основной контент</strong> (блок выше). Ниже в библиотеке выберите тип — например FAQ или CTA — чтобы добавить первый блок.
                </p>
                <button
                    type="button"
                    onclick="{{ $scrollCatalog }}"
                    class="fi-btn fi-btn-color-primary mt-5 inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white outline-none transition"
                >
                    Добавить первый блок
                </button>
            </div>
        @else
            <ul class="m-0 list-none space-y-0 p-0" role="list">
                @foreach($sections as $row)
                    @if(!$loop->first)
                        <li class="flex flex-col items-center py-1 sm:py-1.5" aria-hidden="true">
                            <div class="h-4 w-px bg-gray-200 dark:bg-white/15"></div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">↓</span>
                        </li>
                    @endif
                    <li wire:key="section-row-{{ $row['id'] }}" class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950/40">
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-stretch sm:justify-between sm:gap-4">
                            <div class="flex min-w-0 flex-1 gap-3">
                                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-bold uppercase text-gray-700 dark:bg-white/10 dark:text-gray-200" aria-hidden="true">
                                    {{ strtoupper(substr((string) $row['type_id'], 0, 1)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800 dark:bg-white/10 dark:text-gray-200">#{{ $row['position'] }}</span>
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-500/15 dark:text-primary-200">{{ $row['type_label'] }}</span>
                                        @if($row['is_visible'])
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Виден на сайте</span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-500/20 dark:text-amber-100">Скрыт на сайте</span>
                                        @endif
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Models\PageSection::statuses()[$row['status']] ?? $row['status'] }}</span>
                                    </div>
                                    <p class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['title'] !== '' ? $row['title'] : '— без подписи —' }}</p>
                                    @if(($row['preview'] ?? '') !== '')
                                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $row['preview'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-shrink-0 flex-wrap items-center gap-1 border-t border-gray-100 pt-3 dark:border-white/10 sm:border-t-0 sm:pt-0 sm:pl-2">
                                <button
                                    type="button"
                                    wire:click="moveUp({{ $row['id'] }})"
                                    class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-btn-color-gray fi-btn-outlined rounded-lg px-2 py-1 text-xs"
                                    title="Выше"
                                >↑</button>
                                <button
                                    type="button"
                                    wire:click="moveDown({{ $row['id'] }})"
                                    class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-btn-color-gray fi-btn-outlined rounded-lg px-2 py-1 text-xs"
                                    title="Ниже"
                                >↓</button>
                                <button
                                    type="button"
                                    wire:click="toggleVisibility({{ $row['id'] }})"
                                    class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-btn-color-gray fi-btn-outlined rounded-lg px-2 py-1 text-xs"
                                >{{ $row['is_visible'] ? 'Скрыть' : 'Показать' }}</button>
                                <button
                                    type="button"
                                    wire:click="startEdit({{ $row['id'] }})"
                                    class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-color-primary fi-btn-color-primary fi-btn-outlined rounded-lg px-2 py-1 text-xs"
                                >Изменить</button>
                                <button
                                    type="button"
                                    wire:click="duplicate({{ $row['id'] }})"
                                    class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-btn-color-gray fi-btn-outlined rounded-lg px-2 py-1 text-xs"
                                >Дублировать</button>
                                <button
                                    type="button"
                                    wire:click="delete({{ $row['id'] }})"
                                    wire:confirm="Удалить эту секцию?"
                                    class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-btn-color-danger fi-btn-outlined rounded-lg px-2 py-1 text-xs"
                                >Удалить</button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($sectionCount > 0)
            <div class="mt-5 flex justify-center sm:justify-start">
                <button
                    type="button"
                    onclick="{{ $scrollCatalog }}"
                    class="fi-btn fi-btn-color-primary inline-flex min-h-11 items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white outline-none transition"
                >
                    Добавить секцию
                </button>
            </div>
        @endif
    </div>

    {{-- Secondary: каталог типов --}}
    @if($catalog->isNotEmpty())
        <div
            id="page-section-catalog"
            class="mt-8 rounded-xl border border-gray-200 bg-gray-50/90 p-4 ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/10 sm:p-6"
        >
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Библиотека типов блоков</h4>
            <p class="mt-1 max-w-2xl text-xs text-gray-600 dark:text-gray-400">
                Выберите тип — откроется форма. После сохранения блок появится в списке <strong class="font-medium text-gray-800 dark:text-gray-200">выше</strong> (это не готовая страница, а каталог для вставки).
            </p>

            <div class="mt-5 space-y-5">
                @foreach($catalog as $group)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group['label'] }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($group['items'] as $item)
                                <button
                                    type="button"
                                    wire:click="startAdd('{{ $item['id'] }}')"
                                    class="inline-flex min-h-10 max-w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-sm font-medium text-gray-900 shadow-sm transition hover:border-primary-300 hover:bg-primary-50/50 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:hover:border-primary-500/40 dark:hover:bg-primary-950/30"
                                >
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300" aria-hidden="true">
                                        {{ strtoupper(substr($item['id'], 0, 1)) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate">{{ $item['label'] }}</span>
                                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $item['description'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($showEditor)
        <div
            class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            wire:click="closeEditor"
            wire:key="page-section-editor-backdrop"
        >
            <div
                class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900 sm:p-6"
                wire:click.stop
            >
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h4 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $editingSectionId ? 'Редактирование секции' : 'Новая секция' }}
                    </h4>
                    <button
                        type="button"
                        wire:click="closeEditor"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10"
                    >
                        <span class="sr-only">Закрыть</span>
                        ✕
                    </button>
                </div>
                <div class="space-y-4">
                    {{ $this->sectionEditor }}
                    <div class="flex flex-wrap justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEditor" class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-lg px-4 py-2 text-sm font-semibold">Отмена</button>
                        <button type="button" wire:click="save" class="fi-btn fi-btn-color-primary rounded-lg px-4 py-2 text-sm font-semibold text-white">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.tenant.partials.tenant-public-file-picker-modal', ['uploadSlotAttribute' => 'data-tenant-public-upload-input'])

    <x-filament-actions::modals />
</div>
