@php
    $con = $pm['contrast'] ?? [];
    $painBullets = $con['pain_bullets'] ?? [
        'Клиенты теряются в мессенджерах',
        'Конфликты бронирований и ошибки',
        'Вы тратите часы на ручную работу',
    ];
    $leftItems = $con['left_items'] ?? [];
    /** Позиции тегов = классы Tailwind (без inline style — иначе CSS-линтер ломается на {{ }}). */
    $chaosNodeSlots = [
        ['tw' => 'top-[9%] left-[7%]'],
        ['tw' => 'top-[7%] left-[68%]'],
        ['tw' => 'top-[62%] left-[68%]'],
        ['tw' => 'top-[64%] left-[7%]'],
    ];
@endphp
<section id="contrast" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-slate-50" aria-labelledby="contrast-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6 text-center">
        <h2 id="contrast-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">
            {!! $con['headline'] ?? '' !!}
        </h2>

        <div class="fade-reveal mt-10 rounded-3xl bg-gradient-to-b from-rose-50/80 via-slate-50/90 to-emerald-50/80 p-5 [transition-delay:200ms] sm:mt-12 sm:p-7 lg:p-8">
            <div class="grid grid-cols-1 items-stretch gap-10 sm:gap-12 lg:grid-cols-2 lg:items-stretch lg:gap-14 xl:gap-16">
                <!-- Left: Chaos -->
                <div class="pm-chaos-panel relative rounded-3xl border border-red-200/90 bg-white p-6 shadow-lg shadow-red-900/5 transition-transform hover:scale-[1.01] sm:p-8">
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-red-500 px-4 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-sm">ХАОС</div>
                    <h3 class="text-left text-xl font-extrabold text-slate-900">{!! $con['left_title'] ?? '' !!}</h3>
                    <p class="mt-2 flex items-start gap-2 text-left text-sm font-semibold text-red-600 sm:items-center">
                        <span class="shrink-0 pt-0.5 sm:pt-0" aria-hidden="true">❌</span>
                        <span>{!! $con['left_subtitle'] ?? '' !!}</span>
                    </p>

                    <div class="relative mt-8 h-52 w-full overflow-hidden rounded-xl bg-gradient-to-br from-slate-100 via-rose-50/40 to-slate-100 p-3 sm:h-56 sm:p-4">
                        <div class="pointer-events-none absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(#64748b 1px, transparent 1px); background-size: 10px 10px;" aria-hidden="true"></div>
                        {{-- Плохие связи: больше пересечений и «шума», dash-анимация (в т.ч. на мобиле) --}}
                        <svg class="pm-chaos-wires pointer-events-none absolute inset-0 z-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <g class="opacity-[0.92]">
                                <path class="pm-chaos-path pm-chaos-path-delay-0" d="M 14 17 C 40 4 62 62 86 84" />
                                <path class="pm-chaos-path pm-chaos-path-delay-1" d="M 86 15 C 52 24 34 76 13 83" />
                                <path class="pm-chaos-path pm-chaos-path-delay-2" d="M 14 17 Q 52 46 86 15" />
                                <path class="pm-chaos-path pm-chaos-path-delay-3" d="M 86 84 Q 48 50 13 83" />
                                <path class="pm-chaos-path pm-chaos-path-delay-4" d="M 13 83 C 36 36 68 44 86 15" />
                                <path class="pm-chaos-path pm-chaos-path-delay-5" d="M 86 15 L 49 47 L 14 17" />
                                <path class="pm-chaos-path pm-chaos-path-delay-2" d="M 6 50 Q 50 20 94 50" />
                                <path class="pm-chaos-path pm-chaos-path-delay-4" d="M 50 8 Q 30 50 50 92" />
                                <path class="pm-chaos-path pm-chaos-path-delay-1" d="M 92 28 L 48 52 L 8 72" />
                            </g>
                            <circle class="pm-chaos-fault pm-chaos-fault-delay-0" cx="49" cy="46" r="1.25" />
                            <circle class="pm-chaos-fault pm-chaos-fault-delay-1" cx="44" cy="54" r="1.05" />
                            <circle class="pm-chaos-fault pm-chaos-fault-delay-2" cx="55" cy="51" r="1.05" />
                            <circle class="pm-chaos-fault pm-chaos-fault-delay-3" cx="51" cy="40" r="0.95" />
                            <circle class="pm-chaos-fault pm-chaos-fault-delay-0" cx="48" cy="52" r="0.85" />
                        </svg>
                        @foreach($leftItems as $i => $item)
                            @php $pos = $chaosNodeSlots[$i % count($chaosNodeSlots)]; @endphp
                            <div @class([
                                'absolute z-10 flex max-w-[min(46%,11rem)] items-center gap-2 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-bold leading-tight text-slate-900 shadow-sm ring-1 ring-white/80 sm:max-w-[13rem] sm:px-3 sm:py-2 sm:text-xs',
                                $pos['tw'],
                            ])>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-red-400" aria-hidden="true"></span>
                                {{ $item }}
                            </div>
                        @endforeach
                    </div>
                    <ul class="mt-8 flex flex-col gap-3.5 text-left">
                        @foreach($painBullets as $line)
                            <li class="flex items-start gap-3 text-sm font-medium leading-snug text-slate-800 sm:text-base">
                                <span class="shrink-0 text-red-500" aria-hidden="true">❌</span>
                                <span>{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $line) !!}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Right: RentBase Order -->
                <div class="relative rounded-3xl border border-emerald-200/90 bg-white p-6 shadow-xl shadow-emerald-900/10 ring-2 ring-emerald-100/90 transition-transform hover:scale-[1.01] sm:p-8">
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-emerald-500 px-4 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-sm">ПОРЯДОК</div>
                    <h3 class="text-left text-xl font-extrabold text-slate-900">{!! $con['right_title'] ?? '' !!}</h3>
                    <p class="mt-2 flex items-start gap-2 text-left text-sm font-semibold text-emerald-700 sm:items-center">
                        <span class="shrink-0 pt-0.5 sm:pt-0" aria-hidden="true">✅</span>
                        <span>{!! $con['right_subtitle'] ?? '' !!}</span>
                    </p>

                    <div class="relative mt-8 h-52 w-full overflow-hidden rounded-xl bg-emerald-50/95 p-4 sm:h-56">
                        <!-- Медленно вращающаяся орбита (ядро — отдельно, с glow) -->
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                            <div class="pm-contrast-orbit relative flex h-[11rem] w-[11rem] items-center justify-center sm:h-[12rem] sm:w-[12rem]">
                                <div class="absolute inset-0 rounded-full border-2 border-dashed border-emerald-500/55"></div>
                                <svg class="pointer-events-none absolute inset-0 h-full w-full overflow-visible text-emerald-500" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                                    @foreach([0, 90, 180, 270] as $angle)
                                        @php
                                            $rad = deg2rad($angle - 90);
                                            $ox = round(50 + 42 * cos($rad), 2);
                                            $oy = round(50 + 42 * sin($rad), 2);
                                        @endphp
                                        <circle cx="{{ $ox }}" cy="{{ $oy }}" r="1.35" fill="currentColor" class="[filter:drop-shadow(0_1px_1px_rgb(0_0_0_/_0.06))]"/>
                                    @endforeach
                                </svg>
                            </div>
                        </div>
                        <div class="absolute inset-0 z-10 flex items-center justify-center">
                            <div class="pm-contrast-rb-core relative flex h-[6.25rem] w-[6.25rem] items-center justify-center rounded-full bg-white shadow-xl shadow-emerald-900/15 ring-[10px] ring-emerald-200/95 sm:h-28 sm:w-28 sm:ring-[12px]">
                                <span class="relative z-10 text-2xl font-black tracking-tight text-indigo-700 sm:text-3xl">RB</span>
                            </div>
                        </div>
                        <div class="pointer-events-none absolute inset-0 z-20 text-[11px] font-bold text-slate-900 sm:text-xs">
                            <div class="absolute top-[12%] left-1/2 max-w-[5.5rem] -translate-x-1/2 truncate rounded-lg bg-white/95 px-2.5 py-1.5 text-center shadow-md ring-1 ring-slate-200/80 sm:max-w-none sm:px-3">Сайт</div>
                            <div class="absolute bottom-[12%] left-1/2 max-w-[5.5rem] -translate-x-1/2 truncate rounded-lg bg-white/95 px-2.5 py-1.5 text-center shadow-md ring-1 ring-slate-200/80 sm:max-w-none sm:px-3">CRM</div>
                            <div class="absolute top-1/2 left-[8%] max-w-[5rem] -translate-y-1/2 truncate rounded-lg bg-white/95 px-2.5 py-1.5 text-center shadow-md ring-1 ring-slate-200/80 sm:left-[10%] sm:max-w-none sm:px-3">Брони</div>
                            <div class="absolute top-1/2 right-[8%] max-w-[5rem] -translate-y-1/2 truncate rounded-lg bg-white/95 px-2.5 py-1.5 text-center shadow-md ring-1 ring-slate-200/80 sm:right-[10%] sm:max-w-none sm:px-3">Метрики</div>
                        </div>
                    </div>
                    <ul class="mt-8 flex flex-col gap-3.5 text-left">
                        <li class="flex items-start gap-3 text-sm font-medium leading-snug text-slate-800 sm:text-base"><span class="shrink-0 text-emerald-600" aria-hidden="true">✅</span> Понятно, кто на какой день записан</li>
                        <li class="flex items-start gap-3 text-sm font-medium leading-snug text-slate-800 sm:text-base"><span class="shrink-0 text-emerald-600" aria-hidden="true">✅</span> Меньше ручного переноса в тетрадь</li>
                        <li class="flex items-start gap-3 text-sm font-medium leading-snug text-slate-800 sm:text-base"><span class="shrink-0 text-emerald-600" aria-hidden="true">✅</span> Можно увидеть общую картину за пару кликов</li>
                    </ul>
                </div>
            </div>
        </div>

        <p class="fade-reveal mx-auto mt-10 max-w-3xl text-balance text-base font-bold leading-snug text-slate-900 [transition-delay:300ms] sm:mt-12 sm:text-lg md:text-xl">
            {!! $con['tagline'] ?? '' !!}
        </p>
    </div>
</section>
