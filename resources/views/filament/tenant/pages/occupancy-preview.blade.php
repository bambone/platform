@php
    /** @var \App\Filament\Tenant\Pages\OccupancyPreviewPage $this */
    $resources = $this->tenantSchedulingResources;
    $targets = $this->tenantSchedulingTargets;
    $noResources = $resources->isEmpty();
    $resourceChosen = filled($this->scheduling_resource_id);
    $lockTargetAndDates = $noResources || ! $resourceChosen;
    $resourcesUrl = $this->schedulingResourcesIndexUrl();
    $p = $this->previewPayload;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        @if ($noResources)
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-500/35 dark:bg-amber-500/10 dark:text-amber-50"
                role="status"
            >
                <p class="font-semibold text-amber-950 dark:text-amber-50">Сначала создайте ресурс расписания</p>
                <p class="mt-2 leading-relaxed text-amber-950/90 dark:text-amber-100/90">
                    Здесь показывается занятость для <strong>конкретного ресурса</strong> (например, мастер, зал или пост): что уже заблокировано внутри RentBase и что пришло из подключённых календарей за выбранный период.
                    Пока в кабинете нет ни одного ресурса расписания, выбрать нечего — превью собрать нельзя.
                </p>
                <p class="mt-3">
                    <a
                        href="{{ $resourcesUrl }}"
                        class="font-medium text-amber-950 underline decoration-amber-950/40 underline-offset-2 hover:decoration-amber-950 dark:text-amber-50 dark:decoration-amber-200/50 dark:hover:decoration-amber-100"
                    >
                        Перейти к «Ресурсы расписания»
                    </a>
                    <span class="text-amber-900/80 dark:text-amber-200/80"> — создайте запись и вернитесь на эту страницу.</span>
                </p>
            </div>
        @elseif (! $resourceChosen)
            <div
                class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                role="status"
            >
                <p class="font-medium text-gray-900 dark:text-white">Выберите ресурс</p>
                <p class="mt-1 leading-relaxed">
                    Поля периода и цели (target) станут доступны после выбора ресурса — без него нельзя сопоставить внутренние интервалы и внешний busy.
                </p>
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">Параметры</x-slot>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="occ-res">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">Ресурс</span>
                    </label>
                    <select
                        id="occ-res"
                        wire:model.live="scheduling_resource_id"
                        @disabled($noResources)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        <option value="">—</option>
                        @foreach ($resources as $r)
                            <option value="{{ $r->id }}">{{ $r->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="occ-tgt">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">Target (для internal)</span>
                    </label>
                    <select
                        id="occ-tgt"
                        wire:model.live="scheduling_target_id"
                        @disabled($lockTargetAndDates)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        <option value="">— (только external)</option>
                        @foreach ($targets as $t)
                            <option value="{{ $t->id }}">{{ $t->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label" for="occ-from"><span class="text-sm font-medium text-gray-950 dark:text-white">С (UTC)</span></label>
                    <input
                        id="occ-from"
                        type="date"
                        wire:model.live="range_from"
                        @disabled($lockTargetAndDates)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>
                <div class="space-y-1">
                    <label class="fi-fo-field-wrp-label" for="occ-to"><span class="text-sm font-medium text-gray-950 dark:text-white">По (UTC)</span></label>
                    <input
                        id="occ-to"
                        type="date"
                        wire:model.live="range_to"
                        @disabled($lockTargetAndDates)
                        class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Внутренние интервалы</x-slot>
            @if ($noResources)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Добавьте ресурс расписания — тогда здесь появятся интервалы, которые RentBase считает занятыми (в т.ч. с учётом выбранного target).
                </p>
            @elseif (! $resourceChosen)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Выберите ресурс в блоке «Параметры», чтобы увидеть внутренние интервалы за период.
                </p>
            @elseif ($p['internal'] === [])
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    За выбранный период внутренних интервалов нет. При необходимости уточните target, holds и ручные блокировки по этому ресурсу.
                </p>
            @else
                <ul class="list-inside list-disc text-sm text-gray-950 dark:text-white">
                    @foreach ($p['internal'] as $row)
                        <li>{{ $row['start'] }} — {{ $row['end'] }}</li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Внешний busy (кэш)</x-slot>
            @if ($noResources)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    После появления ресурса и выбора периода здесь отобразятся записи из кэша внешних busy (синхронизация календарей), если они есть.
                </p>
            @elseif (! $resourceChosen)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Выберите ресурс — внешний busy привязан к ресурсу и периоду в UTC.
                </p>
            @elseif ($p['external'] === [])
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Нет записей в external busy за период (или синхронизация ещё не заполнила кэш).
                </p>
            @else
                <ul class="list-inside list-disc text-sm text-gray-950 dark:text-white">
                    @foreach ($p['external'] as $row)
                        <li>{{ $row['start'] }} — {{ $row['end'] }} @if (! empty($row['is_tentative']))<span class="text-amber-600 dark:text-amber-400">(tentative)</span>@endif</li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
