@php
    use App\PageBuilder\PageBuilderIconCatalog;

    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $current = (string) ($getState() ?? '');
    $currentDef = $current !== '' ? PageBuilderIconCatalog::find($current) : null;
    $allowLegacy = $field->isLegacyFallbackAllowed();
    $icons = PageBuilderIconCatalog::forGroup($field->getCatalogGroup());
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Start"
>
    <div
        class="fi-fo-page-builder-icon-picker space-y-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5"
        x-data="{ search: '' }"
    >
        <div class="flex flex-wrap items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-dashed border-gray-300 bg-gray-50 dark:border-white/15 dark:bg-white/5"
                aria-hidden="true"
            >
                @if ($currentDef)
                    {!! svg($currentDef['heroicon'], 'h-6 w-6 text-amber-600 dark:text-amber-400', ['width' => 24, 'height' => 24])->toHtml() !!}
                @else
                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                @endif
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Выберите иконку в сетке ниже или найдите по названию.') }}
            </p>
        </div>

        @if (! $allowLegacy)
            <input
                type="hidden"
                {{ $attributes->merge([
                    $applyStateBindingModifiers('wire:model') => $statePath,
                    'id' => $getId(),
                ], escape: false) }}
            />
        @endif

        <label class="sr-only" for="{{ $getId() }}-search">{{ __('Поиск иконки') }}</label>
        <input
            id="{{ $getId() }}-search"
            type="search"
            x-model="search"
            autocomplete="off"
            placeholder="{{ __('Поиск по названию…') }}"
            class="fi-input block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-950 outline-none ring-1 ring-transparent transition focus:border-amber-500 focus:ring-amber-500/20 disabled:bg-gray-50 disabled:text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-amber-400"
        />

        <div
            class="max-h-52 overflow-y-auto rounded-md border border-gray-100 p-2 dark:border-white/10"
            role="listbox"
            aria-label="{{ __('Доступные иконки') }}"
        >
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
                @foreach ($icons as $icon)
                    @php
                        $needle = strtolower($icon['key'].' '.$icon['label'].' '.implode(' ', $icon['aliases']));
                    @endphp
                    <button
                        type="button"
                        class="flex flex-col items-center gap-1 rounded-md border p-2 text-center transition hover:border-amber-500/40 hover:bg-amber-500/10 dark:border-white/10 dark:hover:border-amber-400/40 @if ($current === $icon['key']) ring-2 ring-amber-500 dark:ring-amber-400 @endif"
                        x-show="!search.trim() || @js($needle).includes(search.toLowerCase().trim())"
                        wire:click="$set('{{ $statePath }}', @js($icon['key']))"
                        wire:key="{{ $getId() }}-{{ $icon['key'] }}"
                        role="option"
                        @if ($current === $icon['key']) aria-selected="true" @else aria-selected="false" @endif
                    >
                        <span class="flex h-8 w-8 items-center justify-center text-gray-800 dark:text-gray-100">
                            {!! svg($icon['heroicon'], 'h-6 w-6', ['width' => 24, 'height' => 24])->toHtml() !!}
                        </span>
                        <span class="line-clamp-2 w-full text-[10px] leading-tight text-gray-600 dark:text-gray-300">{{ $icon['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($allowLegacy)
            <div class="border-t border-gray-100 pt-2 dark:border-white/10">
                <p class="mb-1 text-xs font-medium text-amber-800 dark:text-amber-200">{{ __('Нестандартный ключ (legacy)') }}</p>
                <input
                    id="{{ $getId() }}"
                    type="text"
                    class="fi-input block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                    wire:model.live="{{ $statePath }}"
                    placeholder="{{ __('Только a-z, цифры, - и _') }}"
                />
            </div>
        @endif
    </div>
</x-dynamic-component>
