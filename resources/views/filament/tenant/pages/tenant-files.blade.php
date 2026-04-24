<x-filament-panels::page>
    <div class="space-y-4">
        @if (($q = $this->storageQuota))
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Хранилище клиента') }}</h2>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                            {{ __('Выделено') }}: {{ \Illuminate\Support\Number::fileSize($q->effectiveQuotaBytes, precision: 1) }},
                            {{ __('занято') }}: {{ \Illuminate\Support\Number::fileSize($q->usedBytes, precision: 1) }},
                            {{ __('свободно') }}: {{ \Illuminate\Support\Number::fileSize($q->freeBytes, precision: 1) }}
                        </p>
                    </div>
                    <a
                        href="{{ \App\Filament\Tenant\Pages\StorageMonitoringPage::getUrl() }}"
                        class="text-xs font-medium text-amber-700 underline hover:no-underline dark:text-amber-400"
                    >{{ __('Подробнее в мониторинге') }}</a>
                </div>
                <div class="mb-1 flex justify-between text-xs text-gray-600 dark:text-gray-400">
                    <span>{{ __('Заполнено') }}</span>
                    <span>{{ number_format($q->usedPercent, 1) }}%</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="presentation">
                    @include('filament.tenant.partials.storage-quota-progress-meter', [
                        'usedPercent' => $q->usedPercent,
                        'tier' => $q->progressBarTier(),
                        'variant' => 'page',
                    ])
                </div>
            </div>
        @endif
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Файлы в зонах site, themes и media. Папка themes — только просмотр. Удаление (site и media) проверяет ссылки в БД (страницы, услуги, настройки, каталог медиа) и требует права «Файлы в storage». Метаданные подгружаются порциями по странице списка.') }}
        </p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="tenant-files-search">{{ __('Поиск') }}</label>
                <input
                    id="tenant-files-search"
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    class="fi-input block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                    placeholder="{{ __('Имя или путь…') }}"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="tenant-files-filter">{{ __('Фильтр') }}</label>
                <x-filament.panel-native-select
                    id="tenant-files-filter"
                    wire:model.live="filter"
                    class="min-w-[12rem]"
                >
                    <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_ALL }}">{{ __('Все') }}</option>
                    <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_IMAGES }}">{{ __('Изображения') }}</option>
                    <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_VIDEOS }}">{{ __('Видео') }}</option>
                    <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_DOCUMENTS }}">{{ __('Документы') }}</option>
                    <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_THEMES }}">{{ __('Тема') }}</option>
                    <option value="{{ \App\Services\TenantFiles\TenantFileCatalogService::FILTER_MEDIA }}">{{ __('Медиа') }}</option>
                </x-filament.panel-native-select>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="bg-gray-50 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2">{{ __('Превью') }}</th>
                        <th class="px-3 py-2">{{ __('Путь') }}</th>
                        <th class="px-3 py-2">{{ __('Зона') }}</th>
                        <th class="px-3 py-2">{{ __('Размер') }}</th>
                        <th class="px-3 py-2">{{ __('Изменён') }}</th>
                        <th class="px-3 py-2">{{ __('Действия') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($this->catalogRows as $row)
                        <tr wire:key="tf-row-{{ md5($row['path']) }}" class="text-gray-800 dark:text-gray-200">
                            <td class="px-3 py-2">
                                @if (! empty($row['is_image']) && ! empty($row['public_url']))
                                    <img src="{{ e($row['public_url']) }}" alt="" class="h-10 w-14 rounded object-cover" loading="lazy" />
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="max-w-[min(100%,28rem)] truncate px-3 py-2 font-mono text-xs" title="{{ $row['path'] }}">{{ $row['path_under_zone'] !== '' ? $row['path_under_zone'] : $row['name'] }}</td>
                            <td class="px-3 py-2 text-xs">{{ $row['segment'] }}</td>
                            <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Number::fileSize((int) ($row['size'] ?? 0), precision: 1) }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if (! empty($row['last_modified']))
                                    {{ \Illuminate\Support\Carbon::createFromTimestamp($row['last_modified'])->format('d.m.Y H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-amber-600 hover:underline dark:text-amber-400"
                                        x-data="{ copied: false }"
                                        x-on:click="
                                            navigator.clipboard.writeText(@js($row['path']));
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        "
                                    >
                                        <span x-show="!copied">{{ __('Копировать ключ') }}</span>
                                        <span x-show="copied" x-cloak>{{ __('Скопировано') }}</span>
                                    </button>
                                    @if (! empty($row['public_url']))
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-amber-600 hover:underline dark:text-amber-400"
                                            x-data="{ copiedUrl: false }"
                                            x-on:click="
                                                navigator.clipboard.writeText(@js($row['public_url']));
                                                copiedUrl = true;
                                                setTimeout(() => copiedUrl = false, 2000);
                                            "
                                        >
                                            <span x-show="!copiedUrl">{{ __('Копировать URL') }}</span>
                                            <span x-show="copiedUrl" x-cloak>{{ __('Скопировано') }}</span>
                                        </button>
                                        <a
                                            href="{{ e($row['public_url']) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-300"
                                        >{{ __('Открыть') }}</a>
                                    @endif
                                    @if ($this->isCatalogRowDeletable($row['path']))
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-red-600 hover:underline disabled:opacity-40 dark:text-red-400"
                                            wire:click="deleteFile({{ \Illuminate\Support\Js::from($row['path']) }})"
                                            wire:confirm="{{ e(__('Окончательно удалить файл? Если путь нигде не используется (проверка при удалении) — он исчезнет; иначе удаление будет отклонено.')) }}"
                                            wire:loading.attr="disabled"
                                        >{{ __('Удалить') }}</button>
                                    @else
                                        <span
                                            class="text-xs text-gray-400"
                                            title="{{ e(__('Папка темы: только просмотр.')) }}"
                                        >{{ __('Только просмотр') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Нет файлов.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @php
            $tfPage = $this->filePage;
            $tfLast = $this->fileCatalogLastPage;
            $tfTotal = $this->fileCatalogTotal;
        @endphp
        @if ($tfTotal > 0)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    {{ __('Показано :from–:to из :total', ['from' => ($tfPage - 1) * $this->filesPerPage + 1, 'to' => min($tfTotal, $tfPage * $this->filesPerPage), 'total' => $tfTotal]) }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="gotoFilePage({{ $tfPage - 1 }})"
                        @disabled($tfPage <= 1)
                        class="fi-btn rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-800 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200"
                    >{{ __('Назад') }}</button>
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('Страница :p из :last', ['p' => $tfPage, 'last' => $tfLast]) }}</span>
                    <button
                        type="button"
                        wire:click="gotoFilePage({{ $tfPage + 1 }})"
                        @disabled($tfPage >= $tfLast)
                        class="fi-btn rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-800 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200"
                    >{{ __('Вперёд') }}</button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
