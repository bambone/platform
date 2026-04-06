@php
    $finalHeadline = $pm['cta']['final_headline'] ?? 'Готовы перестать управлять хаосом?';
    $finalSubtitle = $pm['cta']['final_subtitle'] ?? 'Начните работать в системе, а не в наборе инструментов.';
    $finalTrust = $pm['cta']['final_trust'] ?? 'Без риска • Без сложной разработки';
    $finalTrustMicro = array_slice($pm['trust_micro']['final'] ?? [], 0, 3);
    $finalUrgency = trim((string) ($pm['cta']['final_urgency'] ?? ''));
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

    <div class="relative z-10 mx-auto max-w-2xl px-4 text-center sm:px-6">
        <h2 id="cta-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-[1.15] text-white sm:text-4xl md:text-5xl">
            {!! $finalHeadline !!}
        </h2>
        <p class="fade-reveal pm-section-lead text-balance text-base font-medium leading-snug text-slate-300 sm:text-lg [transition-delay:100ms]">
           {!! $finalSubtitle !!}
        </p>
        @if($finalUrgency !== '')
            <p class="fade-reveal mt-4 rounded-xl border border-amber-400/25 bg-amber-500/10 px-4 py-2.5 text-sm font-bold text-amber-100 [transition-delay:160ms]">
                {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $finalUrgency) !!}
            </p>
        @endif

        <div class="fade-reveal mt-8 flex w-full flex-col items-stretch justify-center gap-4 sm:mt-10 [transition-delay:250ms]">
            <a href="{{ $urlLaunch }}" class="group relative inline-flex min-h-14 w-full items-center justify-center overflow-hidden rounded-2xl bg-pm-accent px-8 py-4 text-lg font-extrabold text-white shadow-lg transition-all hover:bg-pm-accent-hover hover:shadow-indigo-500/25 active:scale-[0.98] sm:min-h-[3.75rem] sm:text-xl" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="final">
                <span class="relative z-10">{{ $pm['cta']['primary'] ?? 'Оставить заявку' }}</span>
                <span class="absolute inset-0 z-0 translate-y-full bg-gradient-to-tr from-white/0 via-white/5 to-white/20 transition-transform group-hover:translate-y-0"></span>
            </a>
            <a href="{{ $urlDemo }}" class="text-center text-sm font-semibold text-indigo-200 underline decoration-indigo-400/60 underline-offset-4 transition-colors hover:text-white hover:decoration-white/80 sm:text-base" data-pm-event="cta_click" data-pm-cta="secondary" data-pm-location="final">
                {{ $pm['cta']['secondary'] ?? 'Посмотреть демо' }}
            </a>
        </div>

        <div class="fade-reveal mx-auto mt-8 max-w-lg rounded-2xl border border-white/10 bg-white/[0.06] px-5 py-4 text-center sm:mt-10 sm:px-6 [transition-delay:400ms]">
            <p class="text-sm font-semibold text-slate-100 sm:text-base">
                {!! $finalTrust !!}
            </p>
            @if(!empty($finalTrustMicro))
                <ul class="mt-2 hidden flex-col gap-1.5 text-xs leading-relaxed text-slate-400 sm:mt-3 sm:flex sm:flex-col sm:text-sm">
                    @foreach($finalTrustMicro as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-3 text-xs text-slate-400 sm:text-sm">{{ $pm['cta']['pricing_reassurance'] ?? 'Ответим в течение дня' }}</p>
        </div>
    </div>
</section>
