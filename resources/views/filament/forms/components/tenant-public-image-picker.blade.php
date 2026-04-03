@php
    use App\Services\TenantFiles\TenantFileCatalogService;
    use App\Support\Storage\TenantPublicAssetResolver;

    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $raw = (string) ($getState() ?? '');
    $t = \currentTenant();
    $previewUrl = $t ? TenantPublicAssetResolver::resolve($raw, (int) $t->id) : null;
    $fallbackRel = $field->getThemeFallbackPreviewPath();
    if (($previewUrl === null || $previewUrl === '') && $fallbackRel !== null && $t !== null) {
        $previewUrl = theme_platform_asset_url($fallbackRel, $t);
    }
    $isThemeFallbackPreview = trim($raw) === '' && $fallbackRel !== null && filled($previewUrl);
    $upSel = $field->getUploadSlotSelector();
    $upSub = $field->getUploadPublicSiteSubdirectory();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Start"
>
    <div class="fi-tenant-public-image-picker space-y-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-wrap items-start gap-4">
            <div class="relative flex h-28 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-gray-300 bg-gray-50 dark:border-white/15 dark:bg-white/5">
                @if (filled($previewUrl))
                    <img src="{{ e($previewUrl) }}" alt="" class="max-h-28 w-full object-cover" loading="lazy" />
                    @if ($isThemeFallbackPreview)
                        <span class="absolute bottom-1 left-1 right-1 rounded bg-black/55 px-1 py-0.5 text-center text-[10px] font-medium text-white">{{ __('Фон темы') }}</span>
                    @endif
                @else
                    <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Нет изображения') }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1 space-y-2">
                <input
                    type="text"
                    readonly
                    {{ $attributes->merge([
                        $applyStateBindingModifiers('wire:model') => $statePath,
                        'id' => $getId(),
                    ], escape: false) }}
                    class="fi-input block w-full cursor-default rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                    placeholder="{{ $isThemeFallbackPreview ? __('По умолчанию — фон темы (поле пустое)') : __('Ключ файла или URL появится после выбора') }}"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400"
                        wire:click="openTenantPublicFilePicker(@js($statePath), @js(TenantFileCatalogService::FILTER_IMAGES))"
                    >
                        {{ __('Выбрать из файлов') }}
                    </button>
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        x-on:click="
                            $wire.prepareTenantPublicImageUpload(@js($statePath), @js($upSub));
                            setTimeout(() => document.querySelector(@js($upSel))?.click(), 120);
                        "
                    >
                        {{ __('Загрузить новый') }}
                    </button>
                    @if (filled($raw))
                        <button
                            type="button"
                            class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                            wire:click="clearTenantPublicImageField(@js($statePath))"
                        >
                            {{ $fallbackRel !== null ? __('Сбросить свой фон') : __('Очистить') }}
                        </button>
                    @endif
                    @if (filled($previewUrl))
                        <a
                            href="{{ e($previewUrl) }}"
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
