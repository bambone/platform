<section id="cta-final" class="pm-section-anchor bg-blue-700 py-12 text-white sm:py-16 md:py-20" aria-labelledby="cta-final-heading">
    <div class="mx-auto max-w-6xl px-3 text-center sm:px-4 md:px-6">
        <h2 id="cta-final-heading" class="text-balance text-xl font-bold leading-tight sm:text-2xl md:text-3xl">{{ $pm['cta']['final_headline'] }}</h2>
        <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-blue-100 sm:text-base">{{ $pm['cta']['final_subtitle'] ?? 'Начните принимать заявки уже сегодня' }}</p>
        <div class="mt-8 flex w-full max-w-md flex-col gap-3 sm:max-w-none sm:flex-row sm:flex-wrap sm:justify-center">
            <a href="{{ Route::has('platform.contact') ? route('platform.contact') : url('/contact') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-blue-800 hover:bg-blue-50 sm:w-auto">{{ $pm['cta']['primary'] }}</a>
            <a href="{{ platform_marketing_demo_url() }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-white/80 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto">{{ $pm['cta']['secondary'] }}</a>
        </div>
    </div>
</section>
