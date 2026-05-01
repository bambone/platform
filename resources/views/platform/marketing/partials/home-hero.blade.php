@php
    $heroVariant = $pm['hero_variant'] ?? 'c';
    $heroHeadline = $pm['hero'][$heroVariant] ?? ($pm['hero']['c'] ?? '');
    $heroSubline = trim((string) ($pm['hero_cta_subline'] ?? ''));
    $heroProofFallback = trim((string) ($pm['hero_cta_proof'] ?? ''));
    $heroNext = $pm['hero_next_step'] ?? '';
    $trustBiz = $pm['trust']['businesses'] ?? '';
    $heroTrustMicro = array_slice($pm['trust_micro']['hero'] ?? [], 0, 3);
    $urlLaunch = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
    $urlDemo = platform_marketing_demo_url();
    $heroKicker = trim((string) ($pm['hero_conversion_kicker'] ?? ''));
    if ($heroKicker !== '') {
        $heroKicker = str_replace(':businesses', $trustBiz, $heroKicker);
    }
@endphp
<section id="hero" class="pm-section-anchor relative overflow-x-clip overflow-y-visible border-b border-slate-200 bg-slate-50" aria-labelledby="hero-heading">
    <!-- Background grid and ambient glow (No SVG, No Blur) -->
    <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <!-- Lightweight CSS Grid -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-50"></div>
        <!-- Static Accent Glow with breath animation -->
        <div class="absolute left-1/2 top-0 h-[600px] w-[800px] -translate-x-1/2 -translate-y-1/4 animate-glow-breath rounded-full bg-[radial-gradient(circle_at_center,var(--color-pm-accent),transparent_70%)] opacity-10"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-6xl px-3 pb-14 pt-8 sm:px-4 sm:pb-16 sm:pt-10 md:px-6 md:pb-20 md:pt-14">
        <div class="grid items-start gap-8 sm:gap-10 lg:grid-cols-2 lg:items-center lg:gap-12 xl:gap-16">

            <div class="max-w-2xl lg:max-w-xl">
                <div class="mb-4 inline-flex max-w-full items-center gap-2 rounded-full border border-pm-accent/20 bg-pm-accent/5 px-3 py-1 text-xs font-semibold text-pm-accent fade-reveal sm:mb-5 sm:text-sm [transition-delay:50ms]">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-pm-accent" aria-hidden="true"></span>
                    {!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $pm['hero_badge'] ?? 'Запись и клиенты в одном окне') !!}
                </div>
                <h1 id="hero-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-[1.12] tracking-tight text-slate-900 sm:text-5xl md:text-6xl lg:text-7xl [transition-delay:150ms]">
                    {!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $heroHeadline) !!}
                </h1>
                <p class="fade-reveal pm-section-lead max-w-xl text-pretty text-base leading-snug text-slate-600 sm:text-lg md:text-xl [transition-delay:250ms]">
                    {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['hero_subtitle'] ?? '') !!}
                </p>
                <div class="fade-reveal mt-6 flex w-full max-w-xl flex-col gap-3 sm:mt-8 sm:flex-row sm:gap-4 md:mt-10 [transition-delay:350ms]">
                    <a href="{{ $urlLaunch }}" class="group relative inline-flex min-h-14 w-full items-center justify-center overflow-hidden rounded-2xl bg-pm-accent px-6 py-4 text-lg font-extrabold text-white shadow-xl transition-all hover:bg-pm-accent-hover hover:shadow-pm-accent/25 active:scale-[0.98] sm:min-h-[3.75rem] sm:flex-1 sm:px-8 sm:text-xl" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="hero">
                        <span class="relative z-10">{{ $pm['cta']['primary'] }}</span>
                        <div class="absolute inset-0 z-0 bg-gradient-to-tr from-white/0 via-white/5 to-white/20 opacity-0 transition-opacity group-hover:opacity-100"></div>
                    </a>
                    <a href="{{ $urlDemo }}" class="inline-flex min-h-14 w-full items-center justify-center rounded-2xl border-2 border-pm-accent/80 bg-transparent px-6 py-4 text-base font-bold text-pm-accent shadow-none transition-all hover:border-pm-accent hover:bg-pm-accent/5 active:scale-[0.98] sm:min-h-[3.75rem] sm:w-auto sm:min-w-[10.5rem] sm:px-8 sm:text-lg" data-pm-event="cta_click" data-pm-cta="secondary" data-pm-location="hero">
                        {{ $pm['cta']['secondary'] }}
                    </a>
                </div>

                @if($heroKicker !== '')
                    <p class="fade-reveal mt-5 max-w-xl rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50 to-orange-50/90 px-4 py-3 text-center text-sm font-extrabold leading-snug text-amber-950 shadow-sm ring-1 ring-amber-100 sm:mt-6 sm:px-5 sm:text-base [transition-delay:380ms]">
                        {{ $heroKicker }}
                    </p>
                @endif

                {{-- Micro-proof: после CTA, компактнее на мобиле --}}
                @if(!empty($pm['hero_micro_proof']))
                <div class="fade-reveal mt-6 flex flex-col gap-2.5 sm:mt-8 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-5 sm:gap-y-2 [transition-delay:450ms]">
                    @foreach($pm['hero_micro_proof'] as $i => $proof)
                        <div @class([
                            'flex items-center gap-2 text-xs font-semibold text-slate-500 sm:text-sm',
                            'max-sm:hidden' => $i >= 2,
                        ])>
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 shadow-sm" aria-hidden="true">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            {{ $proof }}
                        </div>
                    @endforeach
                </div>
                @endif
                <p class="fade-reveal mt-4 text-xs text-slate-500 max-sm:mt-3 [transition-delay:480ms]">Подберём показ под вашу нишу — ответим без отписки «спасибо за интерес».</p>
            </div>

            {{-- Один макет + одна карточка --}}
            <div class="pm-hero-mockup fade-reveal relative mx-auto mt-8 w-full max-w-[540px] lg:mt-0 lg:ml-auto xl:max-w-none" style="transition-delay: 200ms;">
                <div class="relative z-10 flex h-[320px] sm:h-[400px] w-full max-w-[540px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex h-10 sm:h-12 flex-none items-center justify-between border-b border-slate-100 bg-slate-50 px-4">
                        <div class="flex gap-1.5" aria-hidden="true">
                            <div class="h-3 w-3 rounded-full bg-slate-300"></div>
                            <div class="h-3 w-3 rounded-full bg-slate-300"></div>
                            <div class="h-3 w-3 rounded-full bg-slate-300"></div>
                        </div>
                        <div class="h-4 sm:h-5 w-24 sm:w-32 rounded-full bg-slate-200/80" aria-hidden="true"></div>
                    </div>
                    <div class="flex min-h-0 flex-1 overflow-hidden">
                        <div class="hidden sm:flex w-16 flex-col items-center gap-4 border-r border-slate-100 bg-slate-50 py-4" aria-hidden="true">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-pm-accent/10">
                                <div class="h-4 w-4 animate-pulse rounded-sm bg-pm-accent"></div>
                            </div>
                            <div class="mt-2 h-6 w-6 rounded bg-slate-200"></div>
                            <div class="h-6 w-6 rounded bg-slate-200"></div>
                            <div class="h-6 w-6 rounded bg-slate-200"></div>
                        </div>
                        <div class="flex flex-1 flex-col gap-4 sm:gap-6 bg-white p-4 sm:p-6" aria-hidden="true">
                            <div class="flex items-center justify-between">
                                <div class="h-5 sm:h-6 w-24 sm:w-32 rounded bg-slate-800"></div>
                                <div class="h-7 sm:h-8 w-20 sm:w-24 animate-pulse-slow rounded-lg bg-pm-accent"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <div class="flex h-20 sm:h-24 flex-col justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 sm:p-4">
                                    <div class="h-2 sm:h-3 w-1/2 rounded bg-slate-300"></div>
                                    <div class="h-4 sm:h-6 w-3/4 animate-pulse-slow rounded bg-slate-800"></div>
                                </div>
                                <div class="flex h-20 sm:h-24 flex-col justify-between rounded-xl border border-slate-100 bg-pm-accent/5 p-3 sm:p-4">
                                    <div class="h-2 sm:h-3 w-[80%] origin-left animate-data-fill-x rounded bg-pm-accent/60 will-change-transform motion-reduce:animate-none"></div>
                                    <div class="h-4 sm:h-6 w-full rounded bg-pm-accent/80"></div>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col gap-2.5 sm:gap-3 lg:hidden">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <div class="h-2 w-6 rounded bg-slate-200"></div>
                                    <div class="h-2 w-12 rounded bg-slate-200"></div>
                                    <div class="h-3 w-10 rounded-full bg-green-100"></div>
                                </div>
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <div class="h-2 w-6 rounded bg-slate-200"></div>
                                    <div class="h-2 w-16 rounded bg-slate-200"></div>
                                    <div class="h-3 w-10 rounded-full bg-amber-100"></div>
                                </div>
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <div class="h-2 w-6 rounded bg-slate-200"></div>
                                    <div class="h-2 w-12 rounded bg-slate-200"></div>
                                    <div class="h-3 w-10 rounded-full bg-green-100"></div>
                                </div>
                            </div>
                            <div class="hidden flex-1 flex-col gap-3 lg:flex">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <div class="h-3 w-8 rounded bg-slate-200"></div>
                                    <div class="h-3 w-16 rounded bg-slate-200"></div>
                                    <div class="h-4 w-12 animate-pulse-slow rounded-full bg-green-100 motion-reduce:animate-none"></div>
                                </div>
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <div class="h-3 w-8 rounded bg-slate-200"></div>
                                    <div class="h-3 w-20 rounded bg-slate-200"></div>
                                    <div class="h-4 w-12 animate-pulse-slow rounded-full bg-amber-100 motion-reduce:animate-none"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    @keyframes notificationPop {
                        0% { transform: translateY(40px) scale(0.95); opacity: 0; }
                        100% { transform: translateY(0) scale(1); opacity: 1; }
                    }
                    @keyframes notificationFloat {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-8px); }
                    }
                    .pm-hero-notification {
                        animation: notificationPop 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards, notificationFloat 6s ease-in-out infinite forwards;
                        animation-delay: 0.6s, 1.4s;
                        opacity: 0;
                        margin: -50px 50px;
                        box-shadow: 0 24px 60px -10px rgba(0,0,0,0.2), 0 4px 12px rgba(0,0,0,0.05); /* Premium depth */
                        background: rgba(255, 255, 255, 0.85);
                        backdrop-filter: blur(24px); /* Glassmorphism */
                        -webkit-backdrop-filter: blur(24px);
                        border: 1px solid rgba(255, 255, 255, 0.7); /* Clean edge, no black border */
                    }
                </style>

                <!-- Demo notification: горизонтально — слева превью, справа блок текста + бейдж -->
                <div class="pm-hero-notification absolute bottom-3 left-3 z-50 flex w-[calc(100%-1.5rem)] max-w-[min(100%,20rem)] items-center gap-3 rounded-2xl p-3.5 will-change-transform motion-reduce:animate-none sm:bottom-[30px] sm:-left-[80px] sm:w-auto sm:max-w-[22rem] sm:gap-4 sm:p-4">
                    {{-- Превью WebP + лицензия: public/img/platform-marketing/hero-booking-thumb.license.txt --}}
                    <div class="relative flex h-12 w-12 shrink-0 rounded-xl bg-slate-100 shadow-md ring-1 ring-black/5 sm:h-14 sm:w-14" aria-hidden="true">
                        <img src="{{ asset('img/platform-marketing/hero-booking-thumb.webp') }}" alt="" width="168" height="168" class="h-full w-full rounded-xl object-cover" decoding="async" />
                        <span class="absolute -right-1.5 -top-1.5 flex h-4 w-4">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none"></span>
                            <span class="relative inline-flex h-4 w-4 rounded-full border-2 border-white bg-green-500 shadow-sm"></span>
                        </span>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                        <div class="min-w-0">
                            <div class="truncate text-[10.5px] font-bold uppercase tracking-widest text-slate-500 sm:text-[11px]">Новая бронь</div>
                            <div
                                class="mt-0.5 truncate text-sm font-extrabold tracking-tight text-slate-900 sm:text-base"
                                title="Расслабленная посадка и ровный ход на дальняк."
                            >HONDA CTX 1300</div>
                        </div>
                        <div class="flex w-fit max-w-full items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700 shadow-sm">
                            <span class="relative flex h-2 w-2 shrink-0">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75 motion-reduce:animate-none"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-wide sm:text-[11px]">Забронировано</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
