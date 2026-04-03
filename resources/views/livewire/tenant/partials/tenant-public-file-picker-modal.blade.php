@php
    /** @var string $uploadSlotAttribute имя data-* атрибута для querySelector, напр. data-tenant-public-upload-input */
    $uploadSlotAttribute = $uploadSlotAttribute ?? 'data-tenant-public-upload-input';
@endphp
{{-- Единый скрытый input загрузки на Livewire-компонент (см. TenantPublicImagePicker). --}}
<input
    type="file"
    accept="image/*"
    {{ $uploadSlotAttribute }}
    wire:model="tenantPublicImageUploadBuffer"
    class="sr-only"
/>

@if ($tenantPublicFilePickerOpen ?? false)
    <div
        class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        wire:click="closeTenantPublicFilePicker"
        wire:key="tenant-public-file-picker-backdrop"
    >
        <div
            class="max-h-[85vh] w-full max-w-4xl overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900"
            wire:click.stop
        >
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Выбор файла') }}</h4>
                <button
                    type="button"
                    wire:click="closeTenantPublicFilePicker"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10"
                >
                    <span class="sr-only">{{ __('Закрыть') }}</span>
                    ✕
                </button>
            </div>
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="tfp-search">{{ __('Поиск по имени') }}</label>
                    <input
                        id="tfp-search"
                        type="search"
                        wire:model.live.debounce.300ms="tenantPublicFilePickerSearch"
                        class="fi-input block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                        placeholder="{{ __('Имя файла…') }}"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="tfp-filter">{{ __('Фильтр') }}</label>
                    <x-filament.panel-native-select
                        id="tfp-filter"
                        wire:model.live="tenantPublicFilePickerFilter"
                        class="min-w-[11rem]"
                    >
                        <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_ALL }}">{{ __('Все') }}</option>
                        <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_IMAGES }}">{{ __('Изображения') }}</option>
                        <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_DOCUMENTS }}">{{ __('Документы') }}</option>
                        <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_THEMES }}">{{ __('Тема') }}</option>
                        <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_MEDIA }}">{{ __('Медиа') }}</option>
                    </x-filament.panel-native-select>
                </div>
            </div>
            <div class="max-h-[55vh] overflow-y-auto border-t border-gray-100 dark:border-white/10">
                <table class="w-full text-left text-xs">
                    <thead class="sticky top-0 bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('Превью') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Файл') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Зона') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Действие') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($tenantPublicFilePickerRows as $row)
                            <tr wire:key="tfp-{{ md5($row['path']) }}" class="text-gray-800 dark:text-gray-200">
                                <td class="px-4 py-2">
                                    @if (! empty($row['is_image']) && ! empty($row['public_url']))
                                        <img src="{{ e($row['public_url']) }}" alt="" class="h-12 w-16 rounded object-cover" loading="lazy" />
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="max-w-[200px] truncate px-4 py-2 font-mono text-[11px]" title="{{ $row['path'] }}">{{ $row['name'] }}</td>
                                <td class="px-4 py-2">{{ $row['segment'] }}</td>
                                <td class="px-4 py-2">
                                    <button
                                        type="button"
                                        class="text-amber-600 hover:underline dark:text-amber-400"
                                        wire:click="pickTenantPublicFile(@js($row['path']))"
                                    >
                                        {{ __('Выбрать') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('Нет файлов по фильтру.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
