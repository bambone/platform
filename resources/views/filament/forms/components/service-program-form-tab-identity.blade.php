@php
    /** @var \Filament\Forms\Components\ViewField $field */
    $title = (string) ($title ?? '');
    $slug = (string) ($slug ?? '');
    $displayTitle = $title !== '' ? $title : 'Без названия';
    $programTypeLabel = (string) ($programTypeLabel ?? '—');
    $coverThumbUrl = $coverThumbUrl ?? null;
    $hasThumb = is_string($coverThumbUrl) && $coverThumbUrl !== '';
    $tabQueryKey = (string) ($tabQueryKey ?? 'service_program_tab');
    $coverTabKey = (string) ($coverTabKey ?? 'cover');
@endphp

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    <div
        class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900/40"
    >
        <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 ring-1 ring-inset ring-primary-600/10 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/20"
            aria-hidden="true"
        >
            {!! svg('heroicon-o-academic-cap', 'h-7 w-7', ['width' => 28, 'height' => 28, 'aria-hidden' => 'true'])->toHtml() !!}
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                {{ $displayTitle }}
            </p>
            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                @if ($slug !== '')
                    <span class="font-mono">/{{ e($slug) }}</span>
                    <span class="text-gray-400 dark:text-gray-500" aria-hidden="true">·</span>
                @endif
                <span>{{ e($programTypeLabel) }}</span>
            </p>
        </div>
        <div class="shrink-0 text-right">
            <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Обложка</p>
            <button
                type="button"
                class="group relative h-[4.5rem] w-28 cursor-pointer overflow-hidden rounded-md border border-gray-200 bg-gray-100 ring-inset transition hover:ring-2 hover:ring-primary-500/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                data-tq="{{ e($tabQueryKey) }}"
                data-ck="{{ e($coverTabKey) }}"
                @click="(() => { const el = $event.currentTarget; const tq = el.getAttribute('data-tq'); const ck = el.getAttribute('data-ck'); if (!tq || !ck) return; const root = el.closest('[wire\\:id]'); if (!root) return; const tabsEl = root.querySelector('.fi-sc-tabs'); if (tabsEl && window.Alpine && typeof Alpine.$data === 'function') { const d = Alpine.$data(tabsEl); if (d && 'tab' in d) d.tab = ck; } const u = new URL(window.location.href); u.searchParams.set(tq, ck); history.replaceState(null, document.title, u.toString()); })()"
            >
                @if ($hasThumb)
                    <img
                        src="{{ e($coverThumbUrl) }}"
                        alt=""
                        class="h-full w-full object-cover"
                        width="160"
                        height="90"
                        loading="lazy"
                        decoding="async"
                    />
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 px-1 text-[10px] text-gray-500 dark:text-gray-400">
                        {!! svg('heroicon-o-photo', 'h-6 w-6 opacity-50', ['width' => 24, 'height' => 24, 'aria-hidden' => 'true'])->toHtml() !!}
                        <span>Нет файла</span>
                    </div>
                @endif
                <span
                    class="absolute inset-0 flex items-end justify-end bg-gradient-to-t from-gray-900/20 to-transparent p-1.5 text-[10px] font-medium text-white opacity-0 transition group-hover:opacity-100"
                >
                    Редактировать
                </span>
            </button>
        </div>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        Клик по миниатюре открывает вкладку «Обложка».
    </p>
</x-dynamic-component>
