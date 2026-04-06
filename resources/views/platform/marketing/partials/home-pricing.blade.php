@php
    $urlBasic = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
    $urlCustom = platform_marketing_contact_url($pm['intent']['custom'] ?? 'custom');
    $p = $pm['pricing'] ?? [];
    $reassurance = $pm['cta']['pricing_reassurance'] ?? 'Ответим в течение дня';
    $supportLine = $pm['cta']['pricing_support_line'] ?? '';
    $planHelp = $p['plan_help'] ?? '';
    $underPrice = $p['under_price'] ?? ['Без команды разработки', 'Без дополнительных интеграторов'];
    $pricingTrustMicro = array_slice($pm['trust_micro']['pricing'] ?? [], 0, 3);
@endphp
<section id="tarify" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-slate-50" aria-labelledby="tarify-heading">
    <div class="relative z-10 mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <h2 id="tarify-heading" class="fade-reveal text-balance text-center text-2xl font-bold leading-tight text-slate-900 sm:text-3xl md:text-4xl">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $p['heading'] ?? 'Начните с минимальными вложениями') !!}</h2>
        <p class="fade-reveal mx-auto mt-4 max-w-2xl text-center text-lg font-semibold text-slate-800 sm:text-xl" style="transition-delay: 60ms;">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $p['intro'] ?? 'Масштабируйтесь без переезда и без смены платформы.') !!}</p>
        <p class="fade-reveal mx-auto mt-3 max-w-2xl text-pretty text-center text-base leading-relaxed text-slate-600" style="transition-delay: 100ms;">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $p['sub_intro'] ?? 'Без разработки. Без интеграций. Без лишних затрат.') !!}</p>
        @if(!empty($p['savings_pitch']))
            <div class="fade-reveal mx-auto mt-6 max-w-2xl rounded-2xl border border-pm-accent/25 bg-gradient-to-br from-indigo-50/90 to-white px-5 py-4 text-center shadow-sm shadow-indigo-900/5 sm:px-6" style="transition-delay: 110ms;">
                <p class="text-pretty text-base font-bold leading-snug text-slate-900 sm:text-lg">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $p['savings_pitch']) !!}</p>
            </div>
        @endif
        @if($planHelp !== '')
            <p class="fade-reveal mx-auto mt-4 max-w-2xl text-center text-sm text-slate-600" style="transition-delay: 120ms;">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $planHelp) !!}</p>
        @endif

        <div class="mx-auto mt-10 grid max-w-4xl gap-6 sm:mt-12 lg:grid-cols-2 lg:gap-10 lg:items-start">

            <!-- Custom / Enterprise — HERO: первым на мобиле, справа на десктопе -->
            <div class="fade-reveal relative order-1 flex cursor-default flex-col overflow-hidden rounded-3xl border-2 border-pm-accent/40 bg-navy p-8 shadow-2xl transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-indigo-500/25 lg:order-2 lg:min-h-[min(100%,28rem)] lg:scale-[1.03] lg:p-9" style="transition-delay: 200ms;">
                <div class="pointer-events-none absolute right-0 top-0 h-72 w-72 translate-x-1/3 -translate-y-1/3 animate-glow-breath rounded-full bg-pm-accent opacity-35 blur-[70px]"></div>

                <div class="relative z-10 flex h-full flex-col">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-xl font-bold text-white lg:text-2xl">{{ $p['custom']['name'] ?? 'Кастомный' }}</h3>
                        <span class="inline-flex rounded-full bg-pm-accent px-3 py-1 text-xs font-extrabold uppercase tracking-wide text-white shadow-md ring-2 ring-white/20">{{ $p['custom_badge'] ?? '🔥 Рекомендуем' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-200 lg:text-base">Для сложных процессов и&nbsp;больших парков.</p>

                    <div class="mt-6 flex flex-col gap-2">
                        <div class="text-[min(2.75rem,9vw)] font-extrabold leading-none tracking-tight text-white">
                            {{ number_format($p['custom']['launch'] ?? 0, 0, ',', ' ') }} ₽ <span class="text-xl font-medium tracking-normal text-slate-400 lg:text-2xl">запуск</span>
                        </div>
                        <div class="inline-flex max-w-fit items-center gap-1.5 rounded-md bg-white/10 px-2.5 py-1 text-sm font-medium text-slate-200">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-pm-accent"></span>
                            {{ number_format($p['custom']['monthly'] ?? 0, 0, ',', ' ') }} ₽ / месяц
                        </div>
                    </div>
                    @if(!empty($underPrice) && is_array($underPrice))
                        <ul class="mt-3 space-y-1 text-sm text-slate-300">
                            @foreach($underPrice as $line)
                                <li>{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $line) !!}</li>
                            @endforeach
                        </ul>
                    @endif

                    <ul class="mt-8 flex-1 space-y-3 text-sm text-slate-200 lg:text-[0.9375rem]">
                        @foreach($p['custom']['bullets'] ?? [] as $b)
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-pm-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $b) !!}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ $urlCustom }}" class="mt-8 block w-full rounded-xl bg-pm-accent py-3.5 text-center text-base font-extrabold tracking-wide text-white shadow-lg transition-colors hover:bg-pm-accent-hover" data-pm-event="cta_click" data-pm-cta="consult" data-pm-location="pricing_custom" data-pm-tier="custom">Обсудить проект</a>
                    <p class="mt-3 text-center text-sm text-slate-300">{{ $reassurance }}</p>
                    @if($supportLine !== '')
                        <p class="mt-1 text-center text-sm text-slate-400">{{ $supportLine }}</p>
                    @endif
                </div>
            </div>

            <!-- Standard Plan -->
            <div class="fade-reveal relative order-2 flex cursor-default flex-col rounded-3xl border border-slate-200 bg-white p-8 shadow-sm transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-indigo-900/10 lg:order-1" style="transition-delay: 350ms;">
                @if(!empty($p['basic_badge']))
                    <p class="mb-3 inline-flex w-fit items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-800">{{ $p['basic_badge'] }}</p>
                @endif
                <h3 class="text-xl font-bold text-slate-900">{{ $p['basic']['name'] ?? 'Бизнес' }}</h3>
                <p class="mt-2 text-sm text-slate-600">Все необходимые инструменты для&nbsp;работы.</p>

                <div class="mt-6 flex flex-col gap-2">
                    <div class="text-[min(2.5rem,8vw)] font-extrabold leading-none tracking-tight text-slate-900">
                        {{ number_format($p['basic']['launch'] ?? 0, 0, ',', ' ') }} ₽ <span class="text-xl font-medium tracking-normal text-slate-400">запуск</span>
                    </div>
                    <div class="inline-flex max-w-fit items-center gap-1.5 rounded-md bg-slate-50 px-2.5 py-1 text-sm font-medium text-slate-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                        {{ number_format($p['basic']['monthly'] ?? 0, 0, ',', ' ') }} ₽ / месяц
                    </div>
                </div>
                @if(!empty($underPrice) && is_array($underPrice))
                    <ul class="mt-3 space-y-1 text-sm text-slate-600">
                        @foreach($underPrice as $line)
                            <li>{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $line) !!}</li>
                        @endforeach
                    </ul>
                @endif

                <ul class="mt-8 flex-1 space-y-3 text-sm text-slate-600">
                    @foreach($p['basic']['bullets'] ?? [] as $b)
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-pm-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $b) !!}</span>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ $urlBasic }}" class="mt-8 block w-full rounded-xl bg-slate-900 py-3 text-center text-sm font-bold tracking-wide text-white transition-colors hover:bg-slate-800" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="pricing_basic" data-pm-tier="basic">{{ $p['basic']['cta_label'] ?? '🚀 Запустить свой сервис' }}</a>
                <p class="mt-3 text-center text-sm text-slate-600">{{ $reassurance }}</p>
                @if($supportLine !== '')
                    <p class="mt-1 text-center text-sm text-slate-600">{{ $supportLine }}</p>
                @endif
                @if(!empty($pricingTrustMicro))
                    <ul class="mt-3 space-y-1 text-center text-sm text-slate-600">
                        @foreach($pricingTrustMicro as $line)
                            <li>{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $line) !!}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>

        @if(!empty($p['footer_choice']))
            <p class="mt-8 text-center text-base text-slate-700">{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $p['footer_choice']) !!}</p>
        @endif
    </div>
</section>
