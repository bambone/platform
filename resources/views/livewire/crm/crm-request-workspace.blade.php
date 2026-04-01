@php
    use App\Models\CrmRequest;
    use App\Models\CrmRequestActivity;
    use App\Product\CRM\BookingWorkspace\BookingTimelineSegmentData;
    use App\Product\CRM\CrmWorkspacePresentation;
    use App\Support\CrmContactPhone;

    /** @var \App\Product\CRM\BookingWorkspace\CrmRequestBookingWorkspaceData $bookingWorkspace */

    $emailRaw = $crm->email;
    $phoneRaw = $crm->phone;
    $emailValid = is_string($emailRaw) && $emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    $phoneDisplay = CrmContactPhone::display(is_string($phoneRaw) ? $phoneRaw : null);
    $phoneTelHref = CrmContactPhone::telHref(is_string($phoneRaw) ? $phoneRaw : null);
    $payload = $crm->payload_json;
    $hasPayloadJson = is_array($payload) && count($payload) > 0;
    $hasTechnicalBlock = filled($crm->ip) || filled($crm->user_agent) || $hasPayloadJson;
    $notesCount = $crm->notes->count();
    $followUpOverdue = $crm->isFollowUpOverdue();
    $followUpUnset = $crm->next_follow_up_at === null;
    $messagePlain = trim((string) ($crm->message ?? ''));
    $hasMessage = $messagePlain !== '';
    $utmPairs = array_filter([
        'utm_source' => $crm->utm_source,
        'utm_medium' => $crm->utm_medium,
        'utm_campaign' => $crm->utm_campaign,
        'utm_content' => $crm->utm_content,
        'utm_term' => $crm->utm_term,
    ], fn ($v) => filled($v));
    $hasUtm = count($utmPairs) > 0;
@endphp

@php
    $card = 'rounded-2xl border border-zinc-200/90 bg-white/60 p-5 shadow-sm dark:border-white/[0.08] dark:bg-zinc-950/50 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.04)]';
    $cardMuted = 'rounded-2xl border border-zinc-200/70 bg-zinc-50/80 p-5 dark:border-white/[0.06] dark:bg-zinc-950/35';
    $secTitle = 'text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-100';
    $secEyebrow = 'text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400';
    $metaText = 'text-sm text-zinc-500 dark:text-zinc-400';
    $helper = 'text-xs text-zinc-500 dark:text-zinc-500';
@endphp

<div class="crm-op-page crm-workspace-root mx-auto min-w-0 max-w-[120rem] text-zinc-900 dark:text-zinc-100">
    {{-- A. Summary header (карточка заявки) --}}
    <header class="crm-op-header {{ $card }} mb-6 border-zinc-300/80 dark:border-white/10 xl:px-8 xl:py-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <p class="{{ $secEyebrow }} mb-1">Обращение</p>
                <h2 class="text-2xl font-bold leading-tight tracking-tight text-zinc-950 sm:text-3xl dark:text-white">
                    {{ $crm->name !== '' ? $crm->name : 'Без имени' }}
                </h2>
                <p class="{{ $metaText }} mt-2 flex flex-wrap gap-x-2 gap-y-1">
                    <span>Создана <time datetime="{{ $crm->created_at?->toIso8601String() }}">{{ $crm->created_at?->format('d.m.Y H:i') ?? '—' }}</time></span>
                    @if($crm->first_viewed_at)
                        <span class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">·</span>
                        <span>Первый просмотр {{ $crm->first_viewed_at->format('d.m.Y H:i') }}</span>
                    @endif
                    @if(filled($crm->source))
                        <span class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">·</span>
                        <span>Источник: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $crm->source }}</span></span>
                    @endif
                    @if(filled($crm->request_type))
                        <span class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">·</span>
                        <span>Тип: <span class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $crm->request_type }}</span></span>
                    @endif
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-col items-stretch gap-3 sm:items-end">
                <div class="flex flex-wrap justify-end gap-2">
                    <span @class([
                        'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold shadow-sm',
                        CrmWorkspacePresentation::statusBadgeClasses($crm->status),
                    ])>
                        {{ CrmRequest::statusLabels()[$crm->status] ?? $crm->status }}
                    </span>
                    <span @class([
                        'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold shadow-sm',
                        CrmWorkspacePresentation::priorityBadgeClasses($crm->priority),
                    ])>
                        {{ CrmRequest::priorityLabels()[$crm->priority ?? CrmRequest::PRIORITY_NORMAL] ?? ($crm->priority ?? '—') }}
                    </span>
                </div>
                <div
                    @class([
                        'flex max-w-md flex-col gap-1 rounded-xl border px-3 py-2.5 sm:items-end sm:text-right',
                        'border-amber-500/40 bg-amber-500/[0.08] dark:border-amber-500/35 dark:bg-amber-500/10' => $followUpOverdue,
                        'border-zinc-200/90 bg-zinc-100/60 dark:border-white/10 dark:bg-white/[0.04]' => ! $followUpOverdue && ! $followUpUnset,
                        'border-dashed border-zinc-300/80 bg-zinc-50/50 dark:border-zinc-600 dark:bg-zinc-900/30' => $followUpUnset,
                    ])
                >
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <x-crm.svg-icon name="heroicon-o-clock" size="sm" @class(['text-amber-600 dark:text-amber-400' => $followUpOverdue]) />
                        Следующий контакт
                    </div>
                    @if($followUpUnset)
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Не запланирован</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-500">Задайте дату напоминания в блоке «Управление заявкой» ниже</p>
                    @elseif($followUpOverdue)
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">{{ $crm->next_follow_up_at?->format('d.m.Y H:i') }}</p>
                        <p class="text-xs font-medium text-amber-800 dark:text-amber-200">Просрочено — свяжитесь с клиентом</p>
                    @else
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $crm->next_follow_up_at?->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
                <p class="{{ $helper }} text-right">
                    Активность: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $crm->last_activity_at?->format('d.m.Y H:i') ?? '—' }}</span>
                    <span class="text-zinc-300 dark:text-zinc-600"> · </span>
                    Заметок: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $notesCount }}</span>
                </p>
            </div>
        </div>
    </header>

    <div class="crm-op-grid">
        {{-- 1. Объект и даты бронирования (первый акцент для оператора проката) --}}
        <section class="crm-op-booking" aria-labelledby="crm-op-booking-title">
            <div class="{{ $card }} crm-op-bw-booking-card crm-op-bw-booking-card--hero">
                <p id="crm-op-booking-title" class="{{ $secEyebrow }} mb-3">Что бронируют</p>
                <div class="crm-op-bw-booking-grid flex flex-col gap-6 xl:flex-row xl:gap-8">
                    <div class="min-w-0 flex-1">
                        <p class="{{ $helper }} mb-2">Объект</p>
                        @if(filled($bookingWorkspace->motorcycleTitle))
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                @if(filled($bookingWorkspace->motorcycleImageUrl))
                                    <div class="crm-op-bw-moto-thumb shrink-0 overflow-hidden rounded-xl border border-zinc-200/80 dark:border-white/10">
                                        <img src="{{ $bookingWorkspace->motorcycleImageUrl }}" alt="" class="h-32 w-full max-w-[14rem] object-cover sm:h-36 sm:w-44" loading="lazy" decoding="async" />
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-xl font-semibold tracking-tight text-zinc-950 dark:text-white xl:text-2xl">{{ $bookingWorkspace->motorcycleTitle }}</p>
                                    @if(filled($bookingWorkspace->motorcycleDescriptor))
                                        <p class="{{ $metaText }} mt-2">{{ $bookingWorkspace->motorcycleDescriptor }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if(filled($bookingWorkspace->motorcycleStatusLabel))
                                            <span class="rounded-md bg-zinc-500/10 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-200">{{ $bookingWorkspace->motorcycleStatusLabel }}</span>
                                        @endif
                                        @if(filled($bookingWorkspace->priceLabel))
                                            <span class="rounded-md bg-primary-500/10 px-2 py-0.5 text-xs font-semibold text-primary-800 dark:text-primary-200">{{ $bookingWorkspace->priceLabel }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @if(filled($bookingWorkspace->adminMotorcycleUrl))
                                            <a href="{{ $bookingWorkspace->adminMotorcycleUrl }}" class="text-sm font-medium text-primary-600 underline decoration-primary-600/30 underline-offset-2 dark:text-primary-400">Объект в кабинете</a>
                                        @endif
                                        @if(filled($bookingWorkspace->adminBookingsIndexUrl))
                                            <a href="{{ $bookingWorkspace->adminBookingsIndexUrl }}" class="text-sm font-medium text-primary-600 underline decoration-primary-600/30 underline-offset-2 dark:text-primary-400">Все брони</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-amber-500/30 bg-amber-500/[0.04] px-4 py-8 text-center dark:border-amber-500/25 dark:bg-amber-500/10">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Объект бронирования не определён</p>
                                <p class="{{ $helper }} mt-1">Заявка без привязки к технике из каталога — календарь занятости ниже недоступен.</p>
                            </div>
                        @endif
                    </div>
                    <div class="crm-op-bw-dates min-w-0 shrink-0 border-t border-zinc-200/70 pt-6 dark:border-white/10 xl:w-[min(100%,22rem)] xl:border-l xl:border-t-0 xl:pl-8 xl:pt-0">
                        <p class="{{ $helper }} mb-2">Запрошенные даты</p>
                        @if(filled($bookingWorkspace->requestedHumanRange))
                            <p class="text-lg font-semibold leading-snug text-zinc-900 dark:text-zinc-100">{{ $bookingWorkspace->requestedHumanRange }}</p>
                            @if(filled($bookingWorkspace->requestedDurationLabel) && $bookingWorkspace->requestedDurationLabel !== '—')
                                <p class="{{ $metaText }} mt-2">Длительность: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $bookingWorkspace->requestedDurationLabel }}</span></p>
                            @endif
                        @else
                            <p class="text-sm text-zinc-500 dark:text-zinc-500">Даты не указаны в канонических данных заявки.</p>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- 2. Сообщение клиента --}}
        <section class="crm-op-msg" aria-labelledby="crm-op-client-msg">
            <div class="{{ $card }} crm-op-msg-card ring-1 ring-primary-500/15 dark:ring-primary-400/20">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <h3 id="crm-op-client-msg" class="{{ $secTitle }}">Сообщение клиента</h3>
                    @if($hasMessage)
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200/90 transition hover:bg-zinc-100 dark:text-zinc-400 dark:ring-white/15 dark:hover:bg-white/5"
                            x-data="{ ok: false }"
                            x-on:click="navigator.clipboard.writeText(@js($messagePlain)).then(() => { ok = true; setTimeout(() => ok = false, 1800) })"
                        >
                            <x-crm.svg-icon name="heroicon-o-clipboard-document" size="sm" class="text-zinc-500 dark:text-zinc-400" />
                            <span x-show="!ok">Скопировать</span>
                            <span x-show="ok" x-cloak>Скопировано</span>
                        </button>
                    @endif
                </div>
                @if($hasMessage)
                    <div class="crm-op-msg-body rounded-xl border border-zinc-200/60 bg-zinc-50/90 px-4 py-5 text-base leading-relaxed text-zinc-900 dark:border-white/10 dark:bg-black/25 dark:text-zinc-100 xl:min-h-[7rem] xl:px-6 xl:py-6 xl:text-lg xl:leading-relaxed">
                        <div class="whitespace-pre-wrap break-words">{{ $crm->message }}</div>
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-zinc-200/90 px-6 py-10 text-center dark:border-white/10">
                        <x-crm.svg-icon name="heroicon-o-chat-bubble-left-ellipsis" size="lg" class="mx-auto text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-400">Клиент не оставил текстового сообщения</p>
                        <p class="{{ $helper }} mt-1">Проверьте контакты справа или историю активности.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- 3. Занятость: горизонтальный календарь по дням --}}
        <section class="crm-op-timeline" aria-labelledby="crm-op-timeline-title">
            <div class="{{ $card }} crm-op-bw-timeline-card">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h3 id="crm-op-timeline-title" class="{{ $secTitle }}">Календарь занятости по дням</h3>
                        @if(filled($bookingWorkspace->timelineWindowHuman))
                            <p class="mt-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $bookingWorkspace->timelineWindowHuman }}</p>
                            <p class="{{ $helper }} mt-1 max-w-3xl">Горизонт — выбранный период вокруг заявки. Подписи — календарные дни. Цвета по легенде; линия «сегодня» показывает текущую дату в часовом поясе тенанта.</p>
                        @elseif(filled($bookingWorkspace->timelineWindowStart) && filled($bookingWorkspace->timelineWindowEnd))
                            <p class="{{ $helper }} mt-1">Период: {{ $bookingWorkspace->timelineWindowStart }} — {{ $bookingWorkspace->timelineWindowEnd }}</p>
                        @endif
                    </div>
                    @php
                        $bwTone = $bookingWorkspace->availabilityBadgeTone;
                    @endphp
                    <span @class([
                        'inline-flex items-center self-start rounded-lg px-2.5 py-1 text-xs font-semibold shadow-sm ring-1',
                        'bg-emerald-500/12 text-emerald-900 ring-emerald-500/25 dark:text-emerald-100' => $bwTone === 'success',
                        'bg-red-500/12 text-red-900 ring-red-500/25 dark:text-red-100' => $bwTone === 'danger',
                        'bg-amber-500/15 text-amber-950 ring-amber-500/30 dark:text-amber-100' => $bwTone === 'warning',
                        'bg-zinc-500/10 text-zinc-800 ring-zinc-500/20 dark:text-zinc-200' => $bwTone === 'neutral',
                        'bg-zinc-500/5 text-zinc-600 ring-zinc-500/15 dark:text-zinc-400' => $bwTone === 'muted',
                    ])>
                        {{ $bookingWorkspace->availabilityStateLabel }}
                    </span>
                </div>
                <p class="mb-4 text-sm leading-snug text-zinc-700 dark:text-zinc-300">{{ $bookingWorkspace->availabilitySummaryText }}</p>

                <div class="crm-op-bw-legend mb-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-zinc-600 dark:text-zinc-400" aria-hidden="true">
                    <span class="inline-flex items-center gap-1.5"><span class="crm-op-bw-legend-swatch crm-op-bw-legend-swatch--requested"></span> Запрос</span>
                    <span class="inline-flex items-center gap-1.5"><span class="crm-op-bw-legend-swatch crm-op-bw-legend-swatch--confirmed"></span> Подтверждено</span>
                    <span class="inline-flex items-center gap-1.5"><span class="crm-op-bw-legend-swatch crm-op-bw-legend-swatch--pending"></span> Ожидает</span>
                    <span class="inline-flex items-center gap-1.5"><span class="crm-op-bw-legend-swatch crm-op-bw-legend-swatch--blocked"></span> Блокировка</span>
                    <span class="inline-flex items-center gap-1.5"><span class="crm-op-bw-legend-swatch crm-op-bw-legend-swatch--today"></span> Сегодня</span>
                </div>

                @if($bookingWorkspace->showTimelinePanel && count($bookingWorkspace->timelineSegments) > 0)
                    <div class="crm-op-bw-cal-shell" role="region" aria-labelledby="crm-op-timeline-title">
                        <div
                            class="crm-op-bw-timeline-track crm-op-bw-timeline-track--gridded rounded-lg border border-zinc-200/80 bg-zinc-100/80 dark:border-white/10 dark:bg-zinc-900/40"
                            role="img"
                            aria-label="Полоса занятости по дням: запрос клиента и брони"
                        >
                            @foreach($bookingWorkspace->timelineAxisTicks as $tick)
                                <x-crm.timeline-offset as="span" class="crm-op-bw-day-grid" :left-percent="$tick->leftPercent" aria-hidden="true" />
                            @endforeach
                            @foreach($bookingWorkspace->timelineSegments as $seg)
                                @if($seg->type === BookingTimelineSegmentData::TYPE_TODAY_MARKER)
                                    <x-crm.timeline-offset
                                        as="span"
                                        class="crm-op-bw-marker"
                                        :left-percent="$seg->leftPercent"
                                        title="{{ implode(' — ', $seg->tooltipLines) }}"
                                    />
                                @else
                                    <span
                                        @class([
                                            'crm-op-bw-seg',
                                            'crm-op-bw-seg--requested' => $seg->type === BookingTimelineSegmentData::TYPE_REQUESTED,
                                            'crm-op-bw-seg--confirmed' => $seg->type === BookingTimelineSegmentData::TYPE_CONFIRMED,
                                            'crm-op-bw-seg--pending' => $seg->type === BookingTimelineSegmentData::TYPE_PENDING,
                                            'crm-op-bw-seg--blocked' => $seg->type === BookingTimelineSegmentData::TYPE_BLOCKED,
                                            'ring-2 ring-red-500/70 dark:ring-red-400/80' => $seg->isConflicting,
                                        ])
                                        @php
                                            $__crmBwSegBox = 'left: '.e((string) $seg->leftPercent).'%; width: '.e((string) $seg->widthPercent).'%;';
                                        @endphp
                                        {!! 'style="'.$__crmBwSegBox.'"' !!}
                                        title="{{ implode(' — ', $seg->tooltipLines) }}"
                                    ></span>
                                @endif
                            @endforeach
                        </div>
                        @if(count($bookingWorkspace->timelineAxisTicks) > 0)
                            <div class="crm-op-bw-axis relative mt-2 min-h-[2.25rem] border-t border-zinc-200/70 pt-1.5 dark:border-white/10" role="presentation">
                                @foreach($bookingWorkspace->timelineAxisTicks as $tick)
                                    <x-crm.timeline-offset
                                        as="span"
                                        @class([
                                            'crm-op-bw-axis-tick absolute top-1.5 max-w-[3.75rem] -translate-x-1/2 text-center text-[10px] leading-tight text-zinc-500 dark:text-zinc-400',
                                            'font-semibold text-zinc-800 dark:text-zinc-200' => $tick->isMajor,
                                        ])
                                        :left-percent="$tick->leftPercent"
                                        title="{{ $tick->isoDate }}"
                                    >{{ $tick->label }}</x-crm.timeline-offset>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-zinc-200/90 px-4 py-8 text-center dark:border-white/10">
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Полоса календаря недоступна</p>
                        <p class="{{ $helper }} mt-1">Нужны объект и полный диапазон дат из заявки.</p>
                    </div>
                @endif

                @if(count($bookingWorkspace->conflictingBookingsCompact) > 0)
                    <div class="crm-op-bw-conflicts mt-5 border-t border-zinc-200/70 pt-4 dark:border-white/10">
                        <p class="{{ $secEyebrow }} mb-2">Пересечения</p>
                        <ul class="space-y-2">
                            @foreach($bookingWorkspace->conflictingBookingsCompact as $row)
                                <li class="flex flex-col gap-1 rounded-lg border border-zinc-200/60 bg-zinc-50/50 px-3 py-2 text-sm dark:border-white/[0.07] dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-500">{{ $row->bookingNumber }}</span>
                                        <span class="mx-1 text-zinc-300 dark:text-zinc-600">·</span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row->customerLabel }}</span>
                                        <p class="{{ $helper }} mt-0.5">{{ $row->dateRangeLabel }} · {{ $row->statusLabel }}</p>
                                    </div>
                                    @if(filled($row->viewUrl))
                                        <a href="{{ $row->viewUrl }}" class="shrink-0 text-sm font-medium text-primary-600 underline dark:text-primary-400">Открыть</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(count($bookingWorkspace->warnings) > 0)
                    <details class="crm-op-bw-warnings mt-4 rounded-lg border border-amber-500/25 bg-amber-500/[0.06] p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <summary class="cursor-pointer text-sm font-semibold text-amber-950 dark:text-amber-100">Служебные предупреждения</summary>
                        <ul class="{{ $helper }} mt-2 list-disc space-y-1 ps-4">
                            @foreach($bookingWorkspace->warnings as $w)
                                <li>{{ $w }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </section>

        {{-- 4. Управление заявкой (после контекста бронирования) --}}
        <aside class="crm-op-aside crm-op-mgmt" aria-labelledby="crm-op-mgmt-title">
            <div class="{{ $card }} crm-op-mgmt-card border-l-[3px] border-l-amber-500/80 pl-[calc(1.25rem-3px)] dark:border-l-amber-500/70 xl:p-7">
                <h3 id="crm-op-mgmt-title" class="{{ $secTitle }} mb-5">Управление заявкой</h3>
                <div class="flex flex-col gap-5">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="ws-status">Статус CRM</label>
                            <div class="flex min-h-[1.25rem] min-w-[5.5rem] items-center justify-end gap-2">
                                <span wire:loading.delay.shortest class="text-[10px] text-zinc-400" wire:target="statusLocal">Сохранение…</span>
                                <span
                                    wire:loading.remove.delay.shortest
                                    wire:target="statusLocal"
                                    class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                    @if($autosaveInlineHint !== 'status') hidden @endif
                                >Сохранено</span>
                            </div>
                        </div>
                        <select
                            id="ws-status"
                            @class([
                                'fi-select-input block w-full rounded-xl border bg-white px-3 py-2.5 text-sm text-zinc-950 focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900/80 dark:text-white',
                                'border-red-500/60' => $errors->has('statusLocal'),
                                'border-zinc-300 dark:border-white/12' => ! $errors->has('statusLocal'),
                            ])
                            wire:model.live.debounce.400ms="statusLocal"
                        >
                            @foreach(CrmRequest::statusLabels() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $helper }} mt-1.5">Сохраняется при смене значения</p>
                        @error('statusLocal')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="ws-priority">Приоритет</label>
                            <div class="flex min-h-[1.25rem] min-w-[5.5rem] items-center justify-end gap-2">
                                <span wire:loading.delay.shortest class="text-[10px] text-zinc-400" wire:target="priorityLocal">Сохранение…</span>
                                <span
                                    wire:loading.remove.delay.shortest
                                    wire:target="priorityLocal"
                                    class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                    @if($autosaveInlineHint !== 'priority') hidden @endif
                                >Сохранено</span>
                            </div>
                        </div>
                        <select
                            id="ws-priority"
                            @class([
                                'fi-select-input block w-full rounded-xl border bg-white px-3 py-2.5 text-sm text-zinc-950 focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900/80 dark:text-white',
                                'border-red-500/60' => $errors->has('priorityLocal'),
                                'border-zinc-300 dark:border-white/12' => ! $errors->has('priorityLocal'),
                            ])
                            wire:model.live.debounce.400ms="priorityLocal"
                        >
                            @foreach(CrmRequest::priorityLabels() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="{{ $helper }} mt-1.5">Сохраняется при смене значения</p>
                        @error('priorityLocal')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <div class="mb-1.5 flex flex-wrap items-center gap-2">
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="ws-followup">Следующий контакт</label>
                            @if($followUpOverdue)
                                <span class="rounded-md bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:text-amber-200">Просрочено</span>
                            @endif
                        </div>
                        <input
                            id="ws-followup"
                            type="datetime-local"
                            wire:model="followUpLocal"
                            @class([
                                'fi-input mb-3 block w-full rounded-xl border bg-white px-3 py-2.5 text-sm text-zinc-950 dark:bg-zinc-900/80 dark:text-white',
                                'border-red-500/60' => $errors->has('followUpLocal'),
                                'border-amber-500/50 ring-1 ring-amber-500/30' => $followUpOverdue && ! $errors->has('followUpLocal'),
                                'border-zinc-300 dark:border-white/12' => ! $followUpOverdue && ! $errors->has('followUpLocal'),
                            ])
                        />
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="saveFollowUp"
                                wire:loading.attr.disabled
                                wire:target="saveFollowUp"
                                class="fi-btn fi-btn-size-sm fi-color-custom fi-btn-color-primary rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="saveFollowUp">Сохранить дату</span>
                                <span wire:loading wire:target="saveFollowUp">Сохранение…</span>
                            </button>
                            <button
                                type="button"
                                wire:click="clearFollowUp"
                                wire:loading.attr.disabled
                                wire:target="clearFollowUp"
                                class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 ring-1 ring-zinc-200 transition hover:bg-zinc-50 disabled:opacity-50 dark:text-zinc-300 dark:ring-white/15 dark:hover:bg-white/5"
                            >
                                <span wire:loading.remove wire:target="clearFollowUp">Сбросить</span>
                                <span wire:loading wire:target="clearFollowUp">…</span>
                            </button>
                        </div>
                        @error('followUpLocal')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </aside>

        {{-- C2. Рабочие заметки --}}
        <section class="crm-op-notes" aria-labelledby="crm-op-notes-title">
            <div class="{{ $card }} crm-op-notes-card">
                <h3 id="crm-op-notes-title" class="{{ $secTitle }} mb-1">Рабочие заметки</h3>
                <p class="{{ $helper }} mb-6">Видят только члены команды в кабинете.</p>

                @if($crm->notes->isEmpty())
                    <div class="mb-8 rounded-2xl border border-dashed border-zinc-200/90 bg-zinc-50/50 px-6 py-10 text-center dark:border-white/10 dark:bg-zinc-900/20">
                        <x-crm.svg-icon name="heroicon-o-chat-bubble-left-right" size="lg" class="mx-auto text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-4 text-base font-medium text-zinc-700 dark:text-zinc-300">Пока нет комментариев</p>
                        <p class="{{ $helper }} mt-2 max-w-sm mx-auto">Напишите заметку ниже — она появится в списке и в ленте активности.</p>
                    </div>
                @else
                    <ul class="mb-8 max-h-80 space-y-3 overflow-y-auto pr-1">
                        @foreach($crm->notes as $note)
                            <li @class([
                                'rounded-2xl border px-4 py-3.5',
                                'border-amber-500/35 bg-amber-500/[0.06] ring-1 ring-amber-500/20 dark:bg-amber-500/10' => $note->is_pinned,
                                'border-zinc-200/80 bg-zinc-50/60 dark:border-white/[0.06] dark:bg-white/[0.03]' => ! $note->is_pinned,
                            ])>
                                <div class="mb-2 flex flex-wrap items-center gap-2 border-b border-zinc-200/60 pb-2 dark:border-white/10">
                                    @if($note->is_pinned)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:text-amber-200">
                                            <x-crm.svg-icon name="heroicon-m-bookmark" size="xs" />
                                            Важно
                                        </span>
                                    @endif
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $note->user?->name ?? 'Система' }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-500">{{ $note->created_at?->format('d.m.Y H:i') }}</span>
                                </div>
                                <div class="whitespace-pre-wrap text-sm leading-relaxed text-zinc-900 dark:text-zinc-100">{{ $note->body }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="crm-op-notes-composer-target rounded-xl border border-zinc-200/70 bg-zinc-50/40 p-4 dark:border-white/[0.07] dark:bg-zinc-900/30" x-data="{ submit() { $wire.addNote() } }" @keydown.ctrl.enter.prevent="submit()" @keydown.meta.enter.prevent="submit()">
                    <label class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200" for="ws-note">Новый комментарий</label>
                    <textarea
                        id="ws-note"
                        wire:model="noteDraft"
                        rows="4"
                        @class([
                            'fi-input mb-3 block w-full rounded-xl border bg-white px-3 py-3 text-sm text-zinc-950 dark:bg-zinc-900/80 dark:text-white',
                            'border-red-500/60' => $errors->has('noteDraft'),
                            'border-zinc-300 dark:border-white/12' => ! $errors->has('noteDraft'),
                        ])
                        placeholder="Внутренний комментарий для команды…"
                    ></textarea>
                    @error('noteDraft')
                        <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-zinc-600 dark:text-zinc-400" for="ws-note-pin">
                            <input
                                id="ws-note-pin"
                                type="checkbox"
                                wire:model="notePinImportant"
                                class="mt-0.5 shrink-0 rounded border-zinc-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-zinc-900"
                            />
                            <span class="min-w-0 leading-snug">Пометить как важное (закрепить сверху)</span>
                        </label>
                        <button
                            type="button"
                            wire:click="addNote"
                            wire:loading.attr.disabled
                            wire:target="addNote"
                            class="fi-btn fi-btn-size-sm fi-color-custom fi-btn-color-primary shrink-0 rounded-xl px-5 py-2.5 text-sm font-semibold disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="addNote">Добавить комментарий</span>
                            <span wire:loading wire:target="addNote">Отправка…</span>
                        </button>
                    </div>
                    <p class="{{ $helper }}">Сочетание Ctrl+Enter отправляет комментарий.</p>
                </div>
            </div>
        </section>

        {{-- Booking insights (row 4 right) --}}
        <section class="crm-op-bw-insights" aria-labelledby="crm-op-bw-insights-title">
            <div class="{{ $cardMuted }} crm-op-bw-insights-card">
                <h3 id="crm-op-bw-insights-title" class="{{ $secTitle }} mb-1">Подсказки по доступности</h3>
                <p class="{{ $helper }} mb-4">Кратко для решения — без дублирования дат и полосы.</p>
                @if($bookingWorkspace->showInsightsPanel)
                    @if(count($bookingWorkspace->insightLines) > 0)
                        <ul class="space-y-2 text-sm leading-relaxed text-zinc-800 dark:text-zinc-200">
                            @foreach($bookingWorkspace->insightLines as $line)
                                <li class="flex gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500/80" aria-hidden="true"></span>
                                    <span>{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-500">Дополнительных подсказок по доступности для текущего состояния нет.</p>
                    @endif
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-500">Для этого обращения блок недоступен (нет контекста бронирования или заявка в контуре платформы).</p>
                @endif
            </div>
        </section>

        {{-- C3. Лента активности --}}
        <section class="crm-op-activity" aria-labelledby="crm-op-activity-title">
            <div class="{{ $cardMuted }} crm-op-activity-card">
                <h3 id="crm-op-activity-title" class="{{ $secTitle }} mb-1">Лента активности</h3>
                <p class="{{ $helper }} mb-6">Хронология действий по обращению (новые сверху).</p>
                @if($crm->activities->isEmpty())
                    <div class="rounded-2xl border border-dashed border-zinc-200/90 px-6 py-10 text-center dark:border-white/10">
                        <x-crm.svg-icon name="heroicon-o-queue-list" size="lg" class="mx-auto text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-4 text-base font-medium text-zinc-700 dark:text-zinc-300">Событий пока нет</p>
                        <p class="{{ $helper }} mt-2">После смены статуса, заметок и напоминаний здесь появится история.</p>
                    </div>
                @else
                    <ul class="crm-op-activity-timeline relative max-h-[28rem] space-y-0 overflow-y-auto border-l-2 border-zinc-200 pl-4 dark:border-white/10">
                        @foreach($crm->activities as $activity)
                            <li class="relative pb-6 ps-2 last:pb-0">
                                <span class="absolute -left-[calc(0.5rem+2px)] top-1.5 flex h-3 w-3 rounded-full border-2 border-white bg-zinc-300 dark:border-zinc-900 dark:bg-zinc-600"></span>
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ CrmRequestActivity::typeLabel($activity->type) }}</span>
                                    <time class="text-xs text-zinc-500 dark:text-zinc-500" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->format('d.m.Y H:i') }}</time>
                                    @if($activity->actor)
                                        <span class="text-xs text-zinc-500 dark:text-zinc-500">· {{ $activity->actor->name }}</span>
                                    @endif
                                </div>
                                @if($activity->summaryLine() !== '')
                                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $activity->summaryLine() }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <div class="crm-op-r2-right">
        {{-- D2. Внутреннее резюме --}}
        <aside class="crm-op-aside crm-op-sum">
            <div class="{{ $card }}">
                <h3 class="{{ $secTitle }} mb-1">Внутреннее резюме</h3>
                <p class="{{ $helper }} mb-4">Короткая выжимка для смены. Сохраняется только по кнопке.</p>
                <textarea
                    id="ws-summary"
                    wire:model="internalSummary"
                    rows="5"
                    placeholder="Кратко: что важно знать следующему оператору…"
                    @class([
                        'fi-input mb-4 block w-full rounded-xl border bg-white px-3 py-3 text-sm text-zinc-950 dark:bg-zinc-900/80 dark:text-white',
                        'border-red-500/60' => $errors->has('internalSummary'),
                        'border-zinc-300 dark:border-white/12' => ! $errors->has('internalSummary'),
                    ])
                ></textarea>
                @error('internalSummary')
                    <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <button
                    type="button"
                    wire:click="saveSummary"
                    wire:loading.attr.disabled
                    wire:target="saveSummary"
                    class="fi-btn fi-btn-size-sm fi-color-custom fi-btn-color-primary rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="saveSummary">Сохранить резюме</span>
                    <span wire:loading wire:target="saveSummary">Сохранение…</span>
                </button>
            </div>
        </aside>

        {{-- D3. Контакты --}}
        <aside class="crm-op-aside crm-op-contacts">
            <div class="{{ $cardMuted }}">
                <h3 class="{{ $secTitle }} mb-4">Контакты</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="{{ $helper }} mb-1">Ответственный</dt>
                        <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $crm->assignedUser?->name ?? '' }}
                            @unless($crm->assignedUser)
                                <span class="font-normal italic text-zinc-500 dark:text-zinc-500">Не назначен</span>
                            @endunless
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $helper }} mb-1">Email</dt>
                        <dd class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
                            @if($emailValid)
                                <a href="mailto:{{ $emailRaw }}" class="break-all font-medium text-primary-600 underline decoration-primary-600/30 underline-offset-2 dark:text-primary-400">{{ $emailRaw }}</a>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-zinc-500 ring-1 ring-zinc-200/90 hover:bg-zinc-100 dark:text-zinc-400 dark:ring-white/15 dark:hover:bg-white/5"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($emailRaw)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                >
                                    <span x-show="!copied">Копировать</span>
                                    <span x-show="copied" x-cloak>Готово</span>
                                </button>
                            @elseif(filled($emailRaw))
                                <span class="break-all text-zinc-800 dark:text-zinc-200">{{ $emailRaw }}</span>
                            @else
                                <span class="italic text-zinc-500 dark:text-zinc-500">Не указан</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $helper }} mb-1">Телефон</dt>
                        <dd class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
                            @if(filled($phoneRaw))
                                @if($phoneTelHref !== null)
                                    <a href="tel:{{ $phoneTelHref }}" class="min-w-0 break-all font-medium text-primary-600 underline decoration-primary-600/30 dark:text-primary-400" title="{{ $phoneRaw }}">{{ $phoneDisplay }}</a>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium text-zinc-500 ring-1 ring-zinc-200/90 hover:bg-zinc-100 dark:text-zinc-400 dark:ring-white/15 dark:hover:bg-white/5"
                                        x-data="{ copied: false }"
                                        x-on:click="navigator.clipboard.writeText(@js($phoneRaw)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                    >
                                        <span x-show="!copied">Копировать</span>
                                        <span x-show="copied" x-cloak>Готово</span>
                                    </button>
                                @else
                                    <span class="min-w-0 break-all text-zinc-800 dark:text-zinc-200" title="{{ $phoneRaw }}">{{ $phoneDisplay }}</span>
                                @endif
                            @else
                                <span class="italic text-zinc-500 dark:text-zinc-500">Не указан</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </aside>
        </div>

        <div class="crm-op-r3-right">
        {{-- D4. Атрибуция + UTM (вторичный блок) --}}
        <aside class="crm-op-aside crm-op-attr">
            <div class="{{ $cardMuted }}">
                <h3 class="{{ $secTitle }} mb-1">Атрибуция и кампании</h3>
                <p class="{{ $helper }} mb-5">Служебные поля для аналитики.</p>
                <dl class="space-y-3 text-sm">
                    <div class="flex flex-col gap-0.5 sm:grid sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-x-3">
                        <dt class="{{ $helper }}">Тип заявки</dt>
                        <dd class="break-words font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $crm->request_type ?: '—' }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:grid sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-x-3">
                        <dt class="{{ $helper }}">Источник</dt>
                        <dd class="break-words font-medium text-zinc-800 dark:text-zinc-200">{{ $crm->source ?: '—' }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:grid sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-x-3">
                        <dt class="{{ $helper }}">Канал</dt>
                        <dd class="break-words text-zinc-800 dark:text-zinc-200">{{ $crm->channel ?: '—' }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:grid sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-x-3">
                        <dt class="{{ $helper }}">Воронка</dt>
                        <dd class="break-words text-zinc-800 dark:text-zinc-200">{{ $crm->pipeline ?: '—' }}</dd>
                    </div>
                    <div class="border-t border-zinc-200/80 pt-3 dark:border-white/10">
                        <dt class="{{ $helper }} mb-1">Страница входа</dt>
                        <dd class="break-all text-xs text-zinc-700 dark:text-zinc-300">{{ filled($crm->landing_page) ? $crm->landing_page : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $helper }} mb-1">Referrer</dt>
                        <dd class="break-all text-xs text-zinc-700 dark:text-zinc-300">{{ filled($crm->referrer) ? $crm->referrer : '—' }}</dd>
                    </div>
                </dl>
                @if($hasUtm)
                    <div class="mt-5 border-t border-zinc-200/80 pt-4 dark:border-white/10">
                        <p class="{{ $secEyebrow }} mb-3">UTM</p>
                        <dl class="space-y-2.5 text-sm">
                            @foreach($utmPairs as $key => $val)
                                <div class="flex flex-col gap-0.5 sm:grid sm:grid-cols-[6.5rem_minmax(0,1fr)] sm:gap-x-2">
                                    <dt class="font-mono text-[11px] text-zinc-500 dark:text-zinc-500">{{ $key }}</dt>
                                    <dd class="break-all text-xs text-zinc-700 dark:text-zinc-300">{{ $val }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @else
                    <p class="{{ $helper }} mt-4 border-t border-zinc-200/80 pt-4 dark:border-white/10">UTM-метки не передавались.</p>
                @endif
            </div>
        </aside>

        {{-- D5. Технические данные --}}
        @if($hasTechnicalBlock)
            <aside class="crm-op-aside crm-op-tech">
                <details class="group {{ $cardMuted }}">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-primary-500 [&::-webkit-details-marker]:hidden">
                        <span class="{{ $secTitle }}">Технические данные</span>
                        <x-crm.svg-icon name="heroicon-o-chevron-down" size="sm" class="shrink-0 text-zinc-400 transition group-open:rotate-180 dark:text-zinc-500" />
                    </summary>
                    <div class="mt-5 space-y-4 border-t border-zinc-200/80 pt-5 dark:border-white/10">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            @if(filled($crm->ip))
                                <div>
                                    <dt class="{{ $helper }}">IP</dt>
                                    <dd class="mt-0.5 font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $crm->ip }}</dd>
                                </div>
                            @endif
                            @if(filled($crm->user_agent))
                                <div class="sm:col-span-2">
                                    <dt class="{{ $helper }}">User-Agent</dt>
                                    <dd class="mt-0.5 break-all font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $crm->user_agent }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if($hasPayloadJson)
                            <pre class="max-h-52 overflow-auto rounded-xl bg-zinc-950 p-4 text-xs leading-relaxed text-zinc-200 dark:bg-black">{{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @else
                            <p class="{{ $helper }}">Дополнительный JSON payload пуст.</p>
                        @endif
                    </div>
                </details>
            </aside>
        @endif
        </div>
    </div>
</div>
