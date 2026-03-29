<section id="hero" class="pm-section-anchor border-b border-slate-200 bg-gradient-to-b from-white to-slate-50" aria-labelledby="hero-heading">
    <div class="mx-auto max-w-6xl px-3 py-12 sm:px-4 sm:py-16 md:px-6 md:py-24">
        <h1 id="hero-heading" class="max-w-3xl text-balance text-[clamp(1.5rem,5vw+0.5rem,3rem)] font-bold leading-tight tracking-tight text-slate-900 md:text-4xl lg:text-5xl">
            {{ platform_marketing_hero_headline() }}
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600 sm:mt-5 sm:text-lg">{{ $pm['hero_subtitle'] }}</p>
        <div class="mt-6 flex flex-col gap-3 sm:mt-8 sm:flex-row sm:flex-wrap">
            <a href="{{ Route::has('platform.contact') ? route('platform.contact') : url('/contact') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 sm:w-auto">{{ $pm['cta']['primary'] }}</a>
            <a href="{{ platform_marketing_demo_url() }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 sm:w-auto">{{ $pm['cta']['secondary'] }}</a>
        </div>
    </div>
</section>
