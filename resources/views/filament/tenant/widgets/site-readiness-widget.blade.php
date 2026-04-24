@php
    $summary = $this->summary;
    $primaryCta = $this->primaryCta;
    $progressWidth = $summary ? min(100, max(0, (int) ($summary['completion_percent'] ?? 0))) : 0;
    $qPct = $summary ? min(100, max(0, (int) ($summary['quick_launch_percent'] ?? 0))) : 0;
    $ePct = $summary ? min(100, max(0, (int) ($summary['extended_percent'] ?? 0))) : 0;
    $qA = (int) ($summary['quick_launch_applicable'] ?? 0);
    $eA = (int) ($summary['extended_applicable'] ?? 0);
    $qC = (int) ($summary['quick_launch_completed'] ?? 0);
    $eC = (int) ($summary['extended_completed'] ?? 0);
    $sessionLabel = $this->sessionStatusLabel;
    $nextItem = $this->nextPendingItem;
    $remaining = $this->remainingCount;
    $whatsNext = $this->whatsNextHint;
    $launchCtx = $this->launchContextSummary;
    $lcRem = (int) ($summary['launch_critical_remaining'] ?? 0);
    $lcTot = (int) ($summary['launch_critical_total'] ?? 0);
@endphp

@if($summary && $primaryCta)
    <x-filament::section>
        <x-slot name="heading">
            Запуск сайта
        </x-slot>
        <x-slot name="description">
            Чеклист мастера — не весь кабинет; состав пунктов можно наращивать.
        </x-slot>

        <div
            class="max-w-5xl space-y-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-950 dark:ring-white/10 sm:p-6"
        >
            {{-- Верхний уровень: главный KPI + следующий шаг + CTA --}}
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
                @if($lcTot > 0)
                    <div class="min-w-0 lg:max-w-md">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">До минимального запуска</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white sm:text-3xl">
                            {{ $lcRem }}
                            <span class="text-lg font-semibold text-gray-500 dark:text-gray-400">/ {{ $lcTot }}</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Критичные шаги для честного старта сайта</p>
                    </div>
                @endif

                <div
                    class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-4"
                >
                    @if(is_array($nextItem) && !empty($nextItem['title']))
                        <div class="min-w-0 flex-1 rounded-xl border border-primary-500/35 bg-primary-50 p-3 dark:border-primary-400/30 dark:bg-primary-950/45">
                            <p class="text-xs font-medium text-primary-800 dark:text-primary-200/90">Следующий шаг</p>
                            <p class="mt-0.5 text-base font-semibold leading-snug text-gray-950 dark:text-white">
                                {{ $nextItem['title'] }}
                            </p>
                        </div>
                    @endif
                    <div class="shrink-0">
                        <x-filament::button tag="a" href="{{ $primaryCta['href'] }}" size="md" color="primary">
                            {{ $primaryCta['label'] }}
                        </x-filament::button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-1 rounded-md bg-primary-600 px-2 py-0.5 text-xs font-semibold text-white dark:bg-primary-500"
                >
                    Запуск сайта
                </span>
                @if($sessionLabel !== '')
                    <span
                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800/90 dark:text-gray-100"
                    >
                        {{ $sessionLabel }}
                    </span>
                @endif
            </div>

            @if(! empty($launchCtx['primary_goal_label']))
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    <span class="font-medium text-gray-800 dark:text-gray-200">Цель:</span>
                    {{ $launchCtx['primary_goal_label'] }}
                </p>
            @endif
            @include('filament.tenant.forms.onboarding-branch-alert', ['resolution' => $this->branchResolution])
            @if(! empty($launchCtx['suppressed_line']))
                <p class="text-xs text-amber-900/90 dark:text-amber-100/85">{{ $launchCtx['suppressed_line'] }}</p>
            @endif
            <p class="text-xs">
                <a
                    href="{{ $launchCtx['overview_url'] }}"
                    class="font-medium text-primary-600 underline decoration-primary-600/30 hover:decoration-primary-600 dark:text-primary-400"
                >
                    Подробнее в обзоре запуска
                </a>
            </p>

            @if($qA > 0 || $eA > 0)
                <div class="grid gap-3 sm:grid-cols-2">
                    @if($qA > 0)
                        <div
                            class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/80"
                        >
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Быстрый запуск</p>
                            <p class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $qC }} из {{ $qA }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $qPct }}%)</span>
                            </p>
                            <svg
                                class="mt-1 h-2 w-full overflow-hidden rounded-full text-gray-200 dark:text-gray-600"
                                viewBox="0 0 100 2"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >
                                <rect width="100" height="2" class="fill-current" />
                                <rect width="{{ $qPct }}" height="2" class="fill-primary-500 dark:fill-primary-400" />
                            </svg>
                        </div>
                    @endif
                    @if($eA > 0)
                        <div
                            class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/80"
                        >
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Расширенный запуск</p>
                            <p class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $eC }} из {{ $eA }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $ePct }}%)</span>
                            </p>
                            <svg
                                class="mt-1 h-2 w-full overflow-hidden rounded-full text-gray-200 dark:text-gray-600"
                                viewBox="0 0 100 2"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >
                                <rect width="100" height="2" class="fill-current" />
                                <rect width="{{ $ePct }}" height="2" class="fill-gray-500 dark:fill-gray-400" />
                            </svg>
                        </div>
                    @endif
                </div>
            @endif

            <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Сводка по чеклисту</span>
                    <span class="text-lg font-semibold text-gray-950 dark:text-white">{{ $summary['completion_percent'] }}%</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        ({{ $summary['completed_count'] }} из {{ $summary['applicable_count'] }})
                    </span>
                </div>
                @if($remaining > 0 && $lcTot === 0)
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Осталось пунктов: <span class="font-medium">{{ $remaining }}</span>
                    </p>
                @endif
                <svg
                    class="mt-1 h-2 w-full overflow-hidden rounded-full text-gray-200 dark:text-gray-600"
                    viewBox="0 0 100 2"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <rect width="100" height="2" class="fill-current" />
                    <rect width="{{ $progressWidth }}" height="2" class="fill-primary-600 dark:fill-primary-400" />
                </svg>
            </div>

            @if($whatsNext !== '')
                <p class="line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $whatsNext }}</p>
            @endif

            @if(! empty($summary['next_pending_items']) && (!is_array($nextItem) || empty($nextItem['title'])))
                <ul class="list-inside list-disc text-sm text-gray-700 dark:text-gray-300">
                    @foreach(array_slice($summary['next_pending_items'], 0, 2) as $item)
                        <li>{{ $item['title'] ?? '' }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
@else
    <x-filament-widgets::widget class="hidden"></x-filament-widgets::widget>
@endif
