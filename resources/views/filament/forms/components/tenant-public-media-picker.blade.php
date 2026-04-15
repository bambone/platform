@php
    use App\Support\Storage\TenantPublicAssetResolver;

    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $raw = (string) ($getState() ?? '');
    $t = \currentTenant();
    $mediaType = $field->getMediaType();
    $catalogFilter = $field->getCatalogFilter();
    $upSub = $field->getUploadPublicSiteSubdirectory();
    $isVideo = $mediaType === \App\Filament\Forms\Components\TenantPublicMediaPicker::MEDIA_VIDEO;
    $upSel = $isVideo ? '[data-tenant-public-video-upload-input]' : $field->getUploadSlotSelector();
    $previewUrl = $t && ! $isVideo ? TenantPublicAssetResolver::resolve($raw, (int) $t->id) : null;
    $resolvedVideoUrl = $t && $isVideo && filled($raw) ? TenantPublicAssetResolver::resolve($raw, (int) $t->id) : null;
    $displayName = '';
    if (filled($raw)) {
        $pathPart = parse_url($raw, PHP_URL_PATH);
        $pathPart = is_string($pathPart) && $pathPart !== '' ? $pathPart : $raw;
        $displayName = basename(str_replace('\\', '/', $pathPart));
    }
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Start"
>
    <div
        class="fi-tenant-public-media-picker space-y-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5"
        x-data="{ manual: false }"
    >
        <div class="flex flex-wrap items-start gap-4">
            @if (! $isVideo)
                <div class="relative flex h-28 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-gray-300 bg-gray-50 dark:border-white/15 dark:bg-white/5">
                    @if (filled($previewUrl))
                        <img src="{{ e($previewUrl) }}" alt="" class="max-h-28 w-full object-cover" loading="lazy" />
                    @else
                        <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Нет изображения') }}</span>
                    @endif
                </div>
            @else
                <div class="flex h-28 w-40 shrink-0 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-300 bg-gray-50 px-2 dark:border-white/15 dark:bg-white/5">
                    <svg class="h-10 w-10 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    @if (filled($raw))
                        <span class="max-w-full truncate text-center text-[10px] font-medium text-gray-700 dark:text-gray-200" title="{{ e($raw) }}">{{ e($displayName) }}</span>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400">{{ __('Файл выбран') }}</span>
                    @else
                        <span class="text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Нет видеофайла') }}</span>
                    @endif
                </div>
            @endif
            <div class="min-w-0 flex-1 space-y-2">
                <input
                    type="text"
                    x-bind:readonly="!manual"
                    @if (filled($ml = $field->getMaxLength())) maxlength="{{ $ml }}" @endif
                    {{ $attributes->merge([
                        $applyStateBindingModifiers('wire:model') => $statePath,
                        'id' => $getId(),
                    ], escape: false) }}
                    x-bind:class="manual ? 'fi-input block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-white' : 'fi-input block w-full cursor-default rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200'"
                    placeholder="{{ $isVideo ? __('Ключ файла MP4/WebM или URL появится после выбора') : __('Ключ файла или URL появится после выбора') }}"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400"
                        x-on:click="manual = false"
                        wire:click="openTenantPublicFilePicker(@js($statePath), @js($catalogFilter))"
                    >
                        {{ __('Выбрать из файлов') }}
                    </button>
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        x-on:click="
                            manual = false;
                            @if ($isVideo)
                                $wire.prepareTenantPublicVideoUpload(@js($statePath), @js($upSub));
                            @else
                                $wire.prepareTenantPublicImageUpload(@js($statePath), @js($upSub));
                            @endif
                            setTimeout(() => document.querySelector(@js($upSel))?.click(), 120);
                        "
                    >
                        {{ __('Загрузить') }}
                    </button>
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        x-on:click="manual = true"
                        x-show="!manual"
                    >
                        {{ __('Указать вручную') }}
                    </button>
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        x-on:click="manual = false"
                        x-show="manual"
                    >
                        {{ __('К выбору из хранилища') }}
                    </button>
                    @if (filled($raw))
                        <button
                            type="button"
                            class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                            wire:click="clearTenantPublicImageField(@js($statePath))"
                        >
                            {{ __('Очистить') }}
                        </button>
                    @endif
                    @if (! $isVideo && filled($previewUrl))
                        <a
                            href="{{ e($previewUrl) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        >
                            {{ __('Открыть') }}
                        </a>
                    @endif
                    @if ($isVideo && filled($resolvedVideoUrl))
                        <a
                            href="{{ e($resolvedVideoUrl) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        >
                            {{ __('Открыть') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
