@php
    use App\Services\TenantFiles\TenantFileCatalogService;
    use App\Support\Storage\TenantPublicAssetResolver;

    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $raw = (string) ($getState() ?? '');
    $mediaKind = (string) ($get('media_kind') ?? '');
    $t = \currentTenant();
    $upSub = $field->getUploadPublicSiteSubdirectory();
    $catalogFilter = TenantFileCatalogService::FILTER_IMAGES;
    $upSel = $field->getUploadSlotSelector();
    $previewUrl = $t && filled($raw) ? TenantPublicAssetResolver::resolve($raw, (int) $t->id) : null;
    $trimmed = trim($raw);
    $isSiteStoragePoster = $trimmed !== ''
        && (
            (preg_match('#^(?:site|storage|tenants)/#', $trimmed) === 1
                || (str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '//')))
        )
        && preg_match('/\.(jpe?g|png|gif|webp|avif|svg)(?:\?.*)?$/i', $trimmed) === 1;
    $isExternalPoster = $trimmed !== '' && preg_match('#^https?://#i', $trimmed) === 1;
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Start"
>
    @if ($mediaKind === 'video_embed')
        <input
            type="hidden"
            {{ $attributes->merge([
                $applyStateBindingModifiers('wire:model') => $statePath,
            ], escape: false) }}
        />
        <div
            class="fi-tenant-public-embed-cover-picker space-y-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5"
            wire:key="embed-cover-{{ $getId() }}-{{ substr(md5($raw), 0, 12) }}"
            x-data="{
                view: @js(filled($raw) ? 'filled' : 'collapsed'),
                urlDraft: '',
                openUrlForm() {
                    this.urlDraft = '';
                    this.view = 'url';
                },
                cancelUrlForm() {
                    this.urlDraft = '';
                    this.view = 'actions';
                },
                saveUrlForm() {
                    const v = this.urlDraft.trim();
                    if (v === '') {
                        return;
                    }
                    $wire.assignTenantPublicLivewireState(@js($statePath), v);
                    this.urlDraft = '';
                },
            }"
        >
            <div x-show="view === 'collapsed'" x-cloak class="space-y-2">
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Обложка не добавлена') }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Необязательно. Нужна для красивого превью в сетке.') }}</p>
                <button
                    type="button"
                    class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400"
                    x-on:click="view = 'actions'"
                >
                    {{ __('Добавить обложку') }}
                </button>
            </div>

            <div x-show="view === 'actions'" x-cloak class="space-y-3">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="relative flex h-28 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-gray-300 bg-gray-50 dark:border-white/15 dark:bg-white/5">
                        <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Превью появится после выбора') }}</span>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400"
                                wire:click="openTenantPublicFilePicker(@js($statePath), @js($catalogFilter))"
                            >
                                {{ __('Выбрать из файлов') }}
                            </button>
                            <button
                                type="button"
                                class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                                x-on:click="
                                    $wire.prepareTenantPublicImageUpload(@js($statePath), @js($upSub));
                                    setTimeout(() => document.querySelector('[data-tenant-public-upload-input]')?.click(), 120);
                                "
                            >
                                {{ __('Загрузить') }}
                            </button>
                            <button
                                type="button"
                                class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                                x-on:click="openUrlForm()"
                            >
                                {{ __('Указать ссылку') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="view === 'url'" x-cloak class="space-y-2">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="{{ $getId() }}-embed-cover-url">{{ __('URL изображения') }}</label>
                <input
                    id="{{ $getId() }}-embed-cover-url"
                    type="url"
                    x-model="urlDraft"
                    class="fi-input block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    placeholder="https://…"
                    autocomplete="off"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400 disabled:opacity-50"
                        x-bind:disabled="urlDraft.trim() === ''"
                        x-on:click="saveUrlForm()"
                    >
                        {{ __('Сохранить') }}
                    </button>
                    <button
                        type="button"
                        class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                        x-on:click="cancelUrlForm()"
                    >
                        {{ __('Отмена') }}
                    </button>
                </div>
            </div>

            <div x-show="view === 'filled'" x-cloak class="space-y-3">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="relative flex h-28 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        @if (filled($previewUrl))
                            <img src="{{ e($previewUrl) }}" alt="" class="max-h-28 w-full object-cover" loading="lazy" />
                        @else
                            <span class="px-2 text-center text-xs text-gray-500 dark:text-gray-400">{{ __('Превью недоступно') }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        @if ($isSiteStoragePoster)
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Из хранилища сайта') }}</p>
                        @elseif ($isExternalPoster)
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Внешняя ссылка') }}</p>
                        @elseif (filled($trimmed))
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Из хранилища сайта') }}</p>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                                x-on:click="view = 'actions'"
                            >
                                {{ __('Заменить') }}
                            </button>
                            <button
                                type="button"
                                class="fi-btn fi-btn-size-sm inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:border-white/20 dark:text-white"
                                wire:click="clearTenantPublicImageField(@js($statePath))"
                            >
                                {{ __('Удалить') }}
                            </button>
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
        </div>
    @elseif ($mediaKind === 'video')
        <div
            class="fi-tenant-public-media-picker space-y-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5"
            wire:key="video-poster-{{ $getId() }}-{{ substr(md5($raw), 0, 12) }}"
            x-data="{ manual: false }"
        >
            <div class="flex flex-wrap items-start gap-4">
                <div class="relative flex h-28 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-gray-300 bg-gray-50 dark:border-white/15 dark:bg-white/5">
                    @if (filled($previewUrl))
                        <img src="{{ e($previewUrl) }}" alt="" class="max-h-28 w-full object-cover" loading="lazy" />
                    @else
                        <span class="px-2 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Нет изображения') }}</span>
                    @endif
                </div>
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
                        placeholder="{{ __('Ключ файла или URL появится после выбора') }}"
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
                                $wire.prepareTenantPublicImageUpload(@js($statePath), @js($upSub));
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
    @endif
</x-dynamic-component>
