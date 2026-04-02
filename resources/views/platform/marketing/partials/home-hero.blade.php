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
@endphp
<section id="hero" class="pm-section-anchor relative overflow-x-clip overflow-y-visible border-b border-slate-200 bg-slate-50" aria-labelledby="hero-heading">
    <!-- Background grid and ambient glow (No SVG, No Blur) -->
    <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <!-- Lightweight CSS Grid -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-50"></div>
        <!-- Static Accent Glow with breath animation -->
        <div class="absolute left-1/2 top-0 h-[600px] w-[800px] -translate-x-1/2 -translate-y-1/4 animate-glow-breath rounded-full bg-[radial-gradient(circle_at_center,var(--color-pm-accent),transparent_70%)] opacity-10"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-6xl px-3 pb-16 pt-8 sm:px-4 sm:pb-24 sm:pt-12 md:px-6 md:pb-32 md:pt-16">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">

            <div class="max-w-2xl lg:max-w-xl">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-pm-accent/20 bg-pm-accent/5 px-3 py-1 text-sm font-semibold text-pm-accent fade-reveal" style="transition-delay: 50ms;">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-pm-accent" aria-hidden="true"></span>
                    {!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $pm['hero_badge'] ?? 'Бронирования и заявки в одном контуре') !!}
                </div>
                <h1 id="hero-heading" class="fade-reveal text-balance text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl md:text-6xl" style="transition-delay: 150ms;">
                    {!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $heroHeadline) !!}
                </h1>
                <p class="fade-reveal mt-6 text-pretty text-lg leading-relaxed text-slate-600 sm:text-xl md:mt-8" style="transition-delay: 250ms;">
                    {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['hero_subtitle'] ?? '') !!}
                </p>
                <div class="fade-reveal mt-8 flex flex-col gap-4 sm:flex-row md:mt-10" style="transition-delay: 350ms;">
                    <a href="{{ $urlLaunch }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-pm-accent px-8 py-3 text-base font-bold text-white shadow-lg transition-colors hover:bg-pm-accent-hover" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="hero">
                        {{ $pm['cta']['primary'] }}
                    </a>
                    <a href="{{ $urlDemo }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-8 py-3 text-base font-semibold text-slate-700 transition-colors hover:bg-slate-50" data-pm-event="cta_click" data-pm-cta="secondary" data-pm-location="hero">
                        {{ $pm['cta']['secondary'] }}
                    </a>
                </div>
                @if($heroSubline !== '')
                    <p class="fade-reveal mt-4 text-pretty text-sm text-slate-500" style="transition-delay: 400ms;">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $heroSubline) !!}</p>
                @elseif($heroProofFallback !== '')
                    <p class="fade-reveal mt-4 text-pretty text-sm font-medium text-slate-600" style="transition-delay: 400ms;">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $heroProofFallback) !!}</p>
                @endif
                <p class="fade-reveal mt-2 text-xs text-slate-400" style="transition-delay: 420ms;">Уже используют {{ $trustBiz }}&nbsp;бизнесов</p>
                @if($heroNext !== '')
                    <p class="fade-reveal mt-2 text-pretty text-sm text-slate-500" style="transition-delay: 450ms;">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $heroNext) !!}</p>
                @endif
                @if(!empty($heroTrustMicro))
                    <ul class="fade-reveal mt-4 flex flex-col gap-1 text-xs text-slate-500 sm:flex-row sm:flex-wrap sm:gap-x-4" style="transition-delay: 480ms;">
                        @foreach($heroTrustMicro as $line)
                            <li class="flex items-center gap-1.5"><span class="h-1 w-1 shrink-0 rounded-full bg-pm-accent" aria-hidden="true"></span>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
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
                        margin: -50px;
                        box-shadow: 0 24px 60px -10px rgba(0,0,0,0.2), 0 4px 12px rgba(0,0,0,0.05); /* Premium depth */
                        background: rgba(255, 255, 255, 0.85);
                        backdrop-filter: blur(24px); /* Glassmorphism */
                        -webkit-backdrop-filter: blur(24px);
                        border: 1px solid rgba(255, 255, 255, 0.7); /* Clean edge, no black border */
                    }
                </style>

                <!-- Gemini-Style Animated Notification Block -->
                <div class="pm-hero-notification absolute bottom-3 left-3 z-50 flex w-[calc(100%-1.5rem)] max-w-[280px] items-center gap-3.5 rounded-2xl p-3.5 will-change-transform motion-reduce:animate-none sm:bottom-[30px] sm:-left-[80px] sm:w-auto sm:max-w-[360px] sm:gap-4 sm:p-4">
                    
                    <!-- App Image/Avatar Style -->
                    <div class="relative flex h-12 w-12 shrink-0 rounded-xl bg-slate-100 shadow-md ring-1 ring-black/5 sm:h-14 sm:w-14" aria-hidden="true">
                        <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&q=80&w=120&h=120" alt="Motorcycle mockup" class="h-full w-full rounded-xl object-cover" />
                        
                        <!-- Pulse Indicator -->
                        <span class="absolute -right-1.5 -top-1.5 flex h-4 w-4">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75 motion-reduce:animate-none"></span>
                            <span class="relative inline-flex h-4 w-4 rounded-full border-2 border-white bg-green-500 shadow-sm"></span>
                        </span>
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[10.5px] font-bold uppercase tracking-widest text-slate-500 sm:text-[11px]">Новая бронь</div>
                        <div class="mt-0.5 truncate text-sm font-extrabold tracking-tight text-slate-900 sm:text-base">BMW S 1000 RR</div>
                    </div>

                    <!-- Status -->
                    <div class="flex shrink-0 items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700 shadow-sm">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75 motion-reduce:animate-none"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wide sm:text-[11px]">Забронировано</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
