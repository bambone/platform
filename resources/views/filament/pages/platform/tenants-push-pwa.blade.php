<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        <span class="font-medium">Доступ</span> — entitlement (тариф, коммерция, оверрайд, канал платформы).
        Колонка <span class="font-medium">Причина</span> — почему нет доступа к функции.
        <span class="font-medium">Кабинет</span> — может ли клиент <span class="font-medium">сохранять</span> настройки; при доступе без сохранения смотрите примечание (часто самообслуживание выключено платформой).
        <span class="font-medium">Push</span> и <span class="font-medium">PWA</span> — переключатели в кабинете.
        Тариф: <span class="font-medium">Платформа → Тарифы</span>; оверрайд: карточка клиента → «Push и PWA (платформа)» или действия в таблице.
    </p>
    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
        Показаны первые <span class="font-medium">500</span> клиентов (по имени). Остальных ищите в разделе «Клиенты».
    </p>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium">Клиент</th>
                    <th class="px-3 py-2 font-medium">План</th>
                    <th class="px-3 py-2 font-medium">Override</th>
                    <th class="px-3 py-2 font-medium">Доступ</th>
                    <th class="px-3 py-2 font-medium">Причина</th>
                    <th class="px-3 py-2 font-medium">Кабинет</th>
                    <th class="px-3 py-2 font-medium">Провайдер</th>
                    <th class="px-3 py-2 font-medium">Подписки (CRM)</th>
                    <th class="px-3 py-2 font-medium">Push</th>
                    <th class="px-3 py-2 font-medium">PWA</th>
                    <th class="px-3 py-2 font-medium w-48">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($this->tableRows as $row)
                    <tr wire:key="tpush-row-{{ $row->tenant->id }}">
                        <td class="px-3 py-2">
                            <a href="{{ $row->editUrl }}" class="text-primary-600 hover:underline">
                                {{ $row->tenantName }}
                            </a>
                        </td>
                        <td class="px-3 py-2">{{ $row->planSlug }}</td>
                        <td class="px-3 py-2">
                            <x-filament::badge :color="$row->overrideBadgeColor">
                                {{ $row->overrideLabel }}
                            </x-filament::badge>
                        </td>
                        <td class="px-3 py-2">
                            @if($row->entitled)
                                <x-filament::badge color="success">да</x-filament::badge>
                            @else
                                <x-filament::badge color="danger">нет</x-filament::badge>
                            @endif
                        </td>
                        <td class="px-3 py-2 max-w-[14rem]">
                            @if($row->entitled)
                                <span class="text-gray-400">—</span>
                            @else
                                <span class="text-gray-700 dark:text-gray-300" title="{{ $row->denialLabel }}">{{ \Illuminate\Support\Str::limit($row->denialLabel, 42) }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 max-w-[12rem]">
                            @if(! $row->entitled)
                                <span class="text-gray-400">—</span>
                            @elseif($row->cabinetCanEdit)
                                <x-filament::badge color="success">сохранение</x-filament::badge>
                            @else
                                <span class="text-xs text-gray-700 dark:text-gray-300" title="{{ $row->cabinetEditNote ?? '' }}">{{ \Illuminate\Support\Str::limit($row->cabinetEditNote ?? 'только просмотр', 36) }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <x-filament::badge :color="$row->providerBadgeColor">
                                {{ $row->providerLabel }}
                            </x-filament::badge>
                        </td>
                        <td class="px-3 py-2">
                            <x-filament::badge :color="$row->subscriptionBadgeColor">
                                {{ $row->subscriptionLabel }}
                            </x-filament::badge>
                        </td>
                        <td class="px-3 py-2">{{ $row->pushCell }}</td>
                        <td class="px-3 py-2">{{ $row->pwaCell }}</td>
                        <td class="px-3 py-2 align-top">
                            <div class="flex flex-wrap gap-1">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    wire:click="platformQuickAction({{ $row->tenant->id }}, 'inherit')"
                                    wire:loading.attr="disabled"
                                    wire:target="platformQuickAction"
                                >
                                    Как в тарифе
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="success"
                                    wire:click="platformQuickAction({{ $row->tenant->id }}, 'force_enable')"
                                    wire:loading.attr="disabled"
                                    wire:target="platformQuickAction"
                                >
                                    Вкл
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="danger"
                                    wire:click="platformQuickAction({{ $row->tenant->id }}, 'force_disable')"
                                    wire:confirm="Принудительно выключить Push/PWA для этого клиента?"
                                    wire:loading.attr="disabled"
                                    wire:target="platformQuickAction"
                                >
                                    Выкл
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="warning"
                                    wire:click="platformQuickAction({{ $row->tenant->id }}, 'commercial_on')"
                                    wire:loading.attr="disabled"
                                    wire:target="platformQuickAction"
                                >
                                    Комм. да
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    wire:click="platformQuickAction({{ $row->tenant->id }}, 'commercial_off')"
                                    wire:confirm="Выключить коммерческую активацию Push для этого клиента?"
                                    wire:loading.attr="disabled"
                                    wire:target="platformQuickAction"
                                >
                                    Комм. нет
                                </x-filament::button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
