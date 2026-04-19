@php
    /** @var \App\Filament\Tenant\Pages\SlotDebugPage $this */
    $services = $this->tenantBookableServices;
    $noServices = $services->isEmpty();
    $serviceChosen = filled($this->bookable_service_id);
    $lockDates = $noServices || ! $serviceChosen;
    $servicesUrl = $this->bookableServicesIndexUrl();
    $rows = $this->debugSlots;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        @if ($noServices)
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-500/35 dark:bg-amber-500/10 dark:text-amber-50"
                role="status"
            >
                <p class="font-semibold text-amber-950 dark:text-amber-50">Сначала создайте услугу с записью</p>
                <p class="mt-2 leading-relaxed text-amber-950/90 dark:text-amber-100/90">
                    Отладка слотов строится для <strong>конкретной услуги (запись)</strong> за период в UTC. Пока нет ни одной такой услуги, выбрать нечего — расчёт не запустить.
                </p>
                <p class="mt-3">
                    <a
                        href="{{ $servicesUrl }}"
                        class="font-medium text-amber-950 underline decoration-amber-950/40 underline-offset-2 hover:decoration-amber-950 dark:text-amber-50 dark:decoration-amber-200/50 dark:hover:decoration-amber-100"
                    >
                        Перейти к «Услуги (запись)»
                    </a>
                </p>
            </div>
        @elseif (! $serviceChosen)
            <div
                class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                role="status"
            >
                <p class="font-medium text-gray-900 dark:text-white">Выберите услугу</p>
                <p class="mt-1 leading-relaxed">
                    Период станет доступен после выбора услуги. Если слотов нет — проверьте, что у цели услуги включена запись и у ресурса заданы правила доступности.
                </p>
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">Параметры</x-slot>
            <x-slot name="description">Те же правила, что и у публичного API слотов: weekly rules, исключения, busy, сервисные буферы.</x-slot>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="slot-debug-service">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">Услуга</span>
                    </label>
                    <select
                        id="slot-debug-service"
                        wire:model.live="bookable_service_id"
                        @disabled($noServices)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        <option value="">—</option>
                        @foreach ($services as $svc)
                            <option value="{{ $svc->id }}">{{ $svc->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="slot-debug-from">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">С даты (UTC)</span>
                    </label>
                    <input
                        id="slot-debug-from"
                        type="date"
                        wire:model.live="range_from"
                        @disabled($lockDates)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="slot-debug-to">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">По дату (UTC)</span>
                    </label>
                    <input
                        id="slot-debug-to"
                        type="date"
                        wire:model.live="range_to"
                        @disabled($lockDates)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Результат</x-slot>
            @if ($noServices)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Создайте услугу с онлайн-записью — здесь появятся рассчитанные слоты.
                </p>
            @elseif (! $serviceChosen)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Выберите услугу выше, чтобы увидеть слоты за период.
                </p>
            @elseif ($rows === [])
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    За выбранный период слотов нет. Проверьте правила доступности, исключения, busy и настройки услуги (буферы, цель с включённой записью).
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 font-medium">Начало (UTC)</th>
                                <th class="py-2 pr-4 font-medium">Конец</th>
                                <th class="py-2 pr-4 font-medium">Ресурс</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $row['starts_at_utc'] ?? '' }}</td>
                                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $row['ends_at_utc'] ?? '' }}</td>
                                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $row['scheduling_resource_label'] ?? '' }} (#{{ $row['scheduling_resource_id'] ?? '' }})</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
