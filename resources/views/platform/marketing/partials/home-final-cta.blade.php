@php
    $finalHeadline = $pm['cta']['final_headline'] ?? 'Готовы перестать управлять хаосом?';
    $finalSubtitle = $pm['cta']['final_subtitle'] ?? 'Начните работать в системе, а не в наборе инструментов.';
    $finalTrust = $pm['cta']['final_trust'] ?? 'Без риска • Без сложной разработки';
    $finalTrustMicro = array_slice($pm['trust_micro']['final'] ?? [], 0, 3);
    $urlLaunch = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
    $urlDemo = platform_marketing_demo_url();
@endphp
<section id="final-cta" class="pm-section-anchor relative overflow-hidden bg-slate-950 py-16 sm:py-20 lg:py-24" aria-labelledby="cta-heading">
    <!-- Ambient background -->
    <div class="pointer-events-none absolute inset-0 z-0 opacity-30">
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-emerald-500/50 to-transparent"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,var(--color-pm-accent),transparent_80%)] opacity-20"></div>
    </div>
    <!-- Dot grid: только низ, слабее — не мешает чтению -->
    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-0 h-1/2 opacity-[0.04] [mask-image:linear-gradient(to_top,black,transparent)]" style="background-image: radial-gradient(#fff 1.5px, transparent 1.5px); background-size: 28px 28px;"></div>

    <div class="relative z-10 mx-auto max-w-3xl px-4 text-center sm:px-6">
        <h2 id="cta-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-white sm:text-4xl md:text-5xl">
            {!! $finalHeadline !!}
        </h2>
        <p class="fade-reveal mt-6 text-balance text-lg font-medium leading-snug text-slate-200 sm:mt-7 sm:text-xl" style="transition-delay: 100ms;">
           {!! $finalSubtitle !!}
        </p>

        <div class="fade-reveal mt-10 flex w-full flex-col items-stretch justify-center gap-3 sm:mt-12 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-4" style="transition-delay: 250ms;">
            <a href="{{ $urlLaunch }}" class="group relative inline-flex min-h-12 w-full items-center justify-center overflow-hidden rounded-xl bg-pm-accent px-8 py-3.5 text-base font-bold text-white shadow-lg transition-all hover:bg-pm-accent-hover hover:shadow-indigo-500/25 sm:w-auto sm:min-h-14 sm:px-10 sm:text-lg active:scale-[0.98]" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="final">
                <span class="relative z-10">{{ $pm['cta']['primary'] ?? 'Оставить заявку' }}</span>
                <span class="absolute inset-0 z-0 translate-y-full bg-gradient-to-tr from-white/0 via-white/5 to-white/20 transition-transform group-hover:translate-y-0"></span>
            </a>
            <a href="{{ $urlDemo }}" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border-2 border-white/20 bg-white/5 px-8 py-3.5 text-base font-bold text-white backdrop-blur-sm transition-all hover:border-white/30 hover:bg-white/10 sm:w-auto sm:min-h-14 sm:text-lg active:scale-[0.98]" data-pm-event="cta_click" data-pm-cta="secondary" data-pm-location="final">
                {{ $pm['cta']['secondary'] ?? 'Посмотреть демо' }}
            </a>
        </div>

        <div class="fade-reveal mx-auto mt-10 max-w-xl rounded-2xl border border-white/10 bg-white/[0.06] px-5 py-5 text-left sm:mt-12 sm:px-6 sm:text-center" style="transition-delay: 400ms;">
            <p class="text-base font-semibold text-slate-100">
                {!! $finalTrust !!}
            </p>
            @if(!empty($finalTrustMicro))
                <ul class="mt-3 flex flex-col gap-2 text-sm leading-relaxed text-slate-300 sm:mx-auto sm:max-w-md">
                    @foreach($finalTrustMicro as $line)
                        <li class="sm:text-center">{{ $line }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-4 border-t border-white/10 pt-4 text-sm text-slate-300">{{ $pm['cta']['pricing_reassurance'] ?? 'Ответим в течение дня' }}</p>
        </div>
    </div>
</section>
