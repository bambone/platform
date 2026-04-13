<x-filament-panels::page>
    {{-- Отдельный CSS entry + JS: так в HTML всегда есть явный <link> на booking-calendar-*.css (надёжнее, чем только css-чанк у JS). --}}
    @vite(['resources/css/booking-calendar.css', 'resources/js/booking-calendar.js'])

    <div class="booking-calendar-page space-y-4">
        @if (count($this->calendarContextNavLinks()) > 0)
            @php
                $tenantForTerms = currentTenant();
                $bookingPluralTerm = $tenantForTerms
                    ? app(\App\Terminology\TenantTerminologyService::class)->label($tenantForTerms, \App\Terminology\DomainTermKeys::BOOKING_PLURAL)
                    : 'бронирования';
            @endphp
            <div class="fi-section rounded-xl border border-amber-200/80 bg-amber-50/90 p-4 text-sm shadow-sm ring-1 ring-amber-900/10 dark:border-amber-500/30 dark:bg-amber-950/40 dark:ring-amber-400/20">
                <p class="font-semibold text-amber-950 dark:text-amber-100">Доступность и свободное время</p>
                <p class="mt-1 text-amber-950/90 dark:text-amber-100/90">
                    Здесь — занятость по времени по уже созданным <strong>{{ $bookingPluralTerm }}</strong>.
                    Карточки <strong>программ</strong> и настройка <strong>слотов / графика</strong> — по ссылкам ниже (в т.ч. раздел меню «Запись и расписание», если модуль включён).
                </p>
                <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($this->calendarContextNavLinks() as $link)
                        <li>
                            <a
                                href="{{ $link['url'] }}"
                                class="font-medium text-amber-900 underline decoration-amber-700/50 underline-offset-2 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-50"
                            >{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Фильтры</p>
            <div class="mt-3">
                {{ $this->calendarFiltersForm }}
            </div>
        </div>

        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Легенда</p>
            <ul class="mt-2 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                @foreach (\App\Models\Booking::occupyingStatuses() as $st)
                    @php
                        $style = \App\Bookings\Calendar\BookingStatusPresentation::calendarStyle($st);
                        $swatchStyleAttr = 'style="'.e('background: '.$style['backgroundColor'].'; border: 1px solid '.$style['borderColor']).'"';
                    @endphp
                    <li class="inline-flex items-center gap-2">
                        <span class="inline-block h-3 w-3 rounded-sm ring-1 ring-gray-400/40" {!! $swatchStyleAttr !!}></span>
                        {{ \App\Bookings\Calendar\BookingStatusPresentation::label($st) }}
                    </li>
                @endforeach
                <li class="inline-flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-sm ring-2 ring-red-500/80"></span>
                    {{ $this->legendRentalOverlapDescription }}
                </li>
            </ul>
        </div>

        <div
            id="booking-calendar-host"
            class="booking-calendar-host min-h-[32rem] overflow-hidden rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            wire:ignore
            data-livewire-id="{{ $this->getId() }}"
            data-timezone="{{ e($this->tenantTimezoneForJs()) }}"
            data-initial-view="{{ e($this->initialFcView()) }}"
            data-initial-date="{{ e($this->calDate) }}"
            data-day-max-events="3"
        ></div>
    </div>

    @if ($eventDetail)
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            wire:click="closeEventDetail"
            wire:key="booking-cal-modal-backdrop"
        >
            <div
                class="fi-modal-window relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                role="dialog"
                aria-modal="true"
                wire:click.stop
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $eventDetail['title'] ?? 'Бронирование' }}</h2>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-white"
                        wire:click="closeEventDetail"
                    >
                        <span class="sr-only">Закрыть</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                @php
                    $ep = $eventDetail['extendedProps'] ?? [];
                    $urls = $ep['urls'] ?? [];
                @endphp
                <dl class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    <div><dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Техника</dt><dd>{{ $ep['equipment'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Клиент</dt><dd>{{ $ep['client'] ?? '—' }}</dd></div>
                    @if (! empty($ep['phone']))
                        <div><dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Телефон</dt><dd>{{ $ep['phone'] }}</dd></div>
                    @endif
                    <div><dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Статус</dt><dd>{{ $ep['statusLabel'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Период</dt><dd>{{ $ep['intervalHuman'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Номер</dt><dd>{{ $ep['bookingNumber'] ?? '—' }}</dd></div>
                    @if (! empty($ep['conflict']))
                        <div class="rounded-lg bg-amber-500/10 px-3 py-2 text-amber-800 dark:text-amber-200">Есть пересечение по единице парка в этом диапазоне.</div>
                    @endif
                </dl>
                <div class="mt-6 flex flex-wrap gap-2">
                    @if (! empty($urls['booking']))
                        <a href="{{ $urls['booking'] }}" class="fi-btn fi-btn-color-primary fi-btn-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium">Открыть бронь</a>
                    @endif
                    @if (! empty($urls['crm']))
                        <a href="{{ $urls['crm'] }}" class="fi-btn fi-btn-color-gray fi-btn-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ring-1 ring-gray-300 dark:ring-white/20">CRM-заявка</a>
                    @endif
                    @if (! empty($urls['equipment']))
                        <a href="{{ $urls['equipment'] }}" class="fi-btn fi-btn-color-gray fi-btn-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ring-1 ring-gray-300 dark:ring-white/20">Техника</a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
