@php
    $prf = $pm['proof_tech'] ?? [];
    $proofBadges = $prf['badges'] ?? [
        ['emoji' => '🚀', 'label' => 'Выдерживает рост'],
        ['emoji' => '🔒', 'label' => 'Данные защищены'],
        ['emoji' => '⚙️', 'label' => 'Облачная платформа'],
    ];
    $proofFooter = trim((string) ($prf['footer_line'] ?? ''));
@endphp
<section id="proof" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-slate-900" aria-labelledby="proof-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <div class="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:items-start lg:gap-16">
            <div>
                <h2 id="proof-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-white sm:text-4xl">
                    {!! $prf['headline'] ?? '' !!}
                </h2>
                <p class="fade-reveal mt-5 text-balance text-base leading-relaxed text-slate-100 [transition-delay:100ms] sm:mt-6 sm:text-lg">
                    {!! $prf['subline'] ?? '' !!}
                </p>
                <div class="fade-reveal mt-7 flex flex-wrap gap-3 [transition-delay:150ms] sm:gap-4" role="list">
                    @foreach($proofBadges as $badge)
                        <div class="inline-flex items-center gap-2.5 rounded-full border border-white/20 bg-white/[0.08] px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-black/20 ring-1 ring-white/5 sm:text-base" role="listitem">
                            <span class="text-lg leading-none sm:text-xl" aria-hidden="true">{{ $badge['emoji'] ?? '' }}</span>
                            <span>{{ $badge['label'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-12 space-y-10 sm:space-y-11">
                    @foreach($prf['items'] ?? [] as $i => $item)
                        <div @class(['fade-reveal flex gap-4 sm:gap-5', 'pm-reveal-proof-' . min($i, 5)])>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500/20 text-indigo-300 ring-1 ring-indigo-400/30 sm:h-12 sm:w-12">
                                <svg class="h-6 w-6 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold leading-snug text-white sm:text-lg">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-300 sm:text-base">{!! $item['description'] ?? '' !!}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Мини-интерфейс: ощущение реального продукта, не абстрактные плейсхолдеры --}}
            <div class="fade-reveal relative aspect-square w-full max-w-[480px] [transition-delay:300ms] lg:mx-auto lg:max-w-none">
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <div class="h-56 w-56 rounded-full bg-indigo-500/15 blur-3xl sm:h-64 sm:w-64"></div>
                </div>
                {{-- Спокойные «связи» между панелями --}}
                <svg class="pointer-events-none absolute inset-[10%] z-0 h-[80%] w-[80%] opacity-[0.14]" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M 22 28 Q 50 48 78 28" fill="none" stroke="rgb(129 140 248)" stroke-width="0.35" vector-effect="non-scaling-stroke" stroke-linecap="round"/>
                    <path d="M 78 72 Q 50 52 22 72" fill="none" stroke="rgb(52 211 153)" stroke-width="0.35" vector-effect="non-scaling-stroke" stroke-linecap="round"/>
                    <path d="M 22 72 L 22 28" fill="none" stroke="rgb(148 163 184)" stroke-width="0.25" vector-effect="non-scaling-stroke" stroke-dasharray="2 3"/>
                    <path d="M 78 28 L 78 72" fill="none" stroke="rgb(148 163 184)" stroke-width="0.25" vector-effect="non-scaling-stroke" stroke-dasharray="2 3"/>
                </svg>
                <div class="relative z-10 grid h-full w-full grid-cols-2 gap-3 p-4 sm:gap-4 sm:p-6">
                    {{-- Панель: график нагрузки --}}
                    <div class="pm-proof-mini-card rounded-2xl border border-white/10 bg-white/[0.06] p-3 backdrop-blur-sm sm:p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-[11px]">Нагрузка</span>
                            <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.7)]" title="Онлайн"></span>
                        </div>
                        <div class="mt-4 flex h-16 items-end justify-between gap-1 px-0.5 sm:h-[4.5rem]">
                            <span class="h-[32%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                            <span class="h-[55%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                            <span class="h-[42%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                            <span class="h-[78%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                            <span class="h-[48%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                            <span class="h-[88%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                            <span class="h-[62%] w-1.5 rounded-sm bg-gradient-to-t from-indigo-600/40 to-indigo-300/90 sm:w-2" aria-hidden="true"></span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-[10px] text-slate-500 sm:text-xs">
                            <span>Пн</span><span>Вс</span>
                        </div>
                    </div>
                    {{-- Панель: брони / очередь --}}
                    <div class="pm-proof-mini-card mt-6 rounded-2xl border border-white/10 bg-white/[0.06] p-3 backdrop-blur-sm sm:mt-8 sm:p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-[11px]">Брони</span>
                            <span class="rounded-md bg-indigo-500/25 px-1.5 py-0.5 text-[9px] font-bold text-indigo-200 sm:text-[10px]">+3</span>
                        </div>
                        <ul class="mt-3 space-y-2" aria-hidden="true">
                            @foreach([
                                ['l' => 'BMW S 1000', 's' => 'Подтв.'],
                                ['l' => 'Yamaha MT-09', 's' => 'Ожид.'],
                                ['l' => 'Kawasaki Z900', 's' => 'Подтв.'],
                            ] as $row)
                                <li class="flex items-center justify-between gap-2 rounded-lg bg-white/[0.04] px-2 py-1.5 ring-1 ring-white/[0.06]">
                                    <span class="truncate text-[10px] font-semibold text-slate-200 sm:text-xs">{{ $row['l'] }}</span>
                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold {{ $row['s'] === 'Подтв.' ? 'bg-emerald-500/20 text-emerald-200' : 'bg-amber-500/20 text-amber-100' }}">{{ $row['s'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    {{-- Панель: SLA / очередь задач --}}
                    <div class="pm-proof-mini-card rounded-2xl border border-white/10 bg-white/[0.06] p-3 backdrop-blur-sm sm:p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-[11px]">Очередь</span>
                            <span class="text-[10px] font-bold text-slate-300 sm:text-xs">12 задач</span>
                        </div>
                        <div class="mt-4 space-y-2">
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                <div class="h-full w-[72%] rounded-full bg-gradient-to-r from-emerald-500/80 to-emerald-400/90"></div>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                <div class="h-full w-[45%] rounded-full bg-gradient-to-r from-indigo-500/80 to-indigo-400/90"></div>
                            </div>
                            <p class="text-[10px] leading-relaxed text-slate-500 sm:text-[11px]">Фоновая обработка без зависаний</p>
                        </div>
                    </div>
                    {{-- Панель: домен / SEO сниппет --}}
                    <div class="pm-proof-mini-card mt-6 rounded-2xl border border-white/10 bg-white/[0.06] p-3 backdrop-blur-sm sm:mt-8 sm:p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-[11px]">Витрина</span>
                            <span class="truncate text-[9px] font-medium text-indigo-200/90 sm:text-[10px]">yoursite.ru</span>
                        </div>
                        <div class="mt-3 rounded-lg bg-slate-950/50 p-2 ring-1 ring-white/10">
                            <div class="h-1.5 w-3/4 rounded bg-white/20"></div>
                            <div class="mt-2 h-1 w-full rounded bg-white/10"></div>
                            <div class="mt-1 h-1 w-[85%] rounded bg-white/10"></div>
                            <p class="mt-2 text-[9px] leading-tight text-slate-500 sm:text-[10px]">Индексация и свой домен</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($proofFooter !== '')
            <p class="fade-reveal mx-auto mt-12 max-w-3xl border-t border-white/10 pt-8 text-center text-balance text-lg font-bold leading-snug text-white [transition-delay:450ms] sm:mt-14 sm:text-xl">
                {!! $proofFooter !!}
            </p>
        @endif
    </div>
</section>
