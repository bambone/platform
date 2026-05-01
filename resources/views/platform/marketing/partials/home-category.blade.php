@php
    $cat = $pm['category_shift'] ?? [];
@endphp
<section id="category-shift" class="pm-section-anchor pm-section-y relative overflow-hidden bg-slate-900" aria-labelledby="category-heading">
    <!-- Ambient background -->
    <div class="pointer-events-none absolute inset-0 z-0 opacity-20">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,#6366f1,transparent_50%)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,#10b981,transparent_50%)]"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-5xl px-4 text-center">
        <div class="mb-6 flex justify-center sm:mb-8">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-indigo-300 backdrop-blur-md">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-indigo-400"></span>
                По-другому посмотреть на запись
            </span>
        </div>

        <h2 id="category-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-white sm:text-4xl md:text-5xl">
            {!! $cat['headline'] ?? '' !!}
        </h2>

        <p class="fade-reveal pm-section-lead text-balance text-xl font-bold leading-snug text-white sm:text-2xl md:text-3xl [transition-delay:200ms]">
            {!! $cat['subline'] ?? '' !!}
        </p>

        {{-- Три столпа: центральная карточка — главная (иерархия на мобиле и десктопе) --}}
        <div class="fade-reveal mt-6 grid grid-cols-1 gap-3 md:mt-8 md:grid-cols-3 md:items-stretch md:gap-4 [transition-delay:350ms]">
            <div class="group order-2 flex min-h-full flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-5 py-6 text-center transition-all hover:bg-white/[0.06] sm:px-6 sm:py-7 md:order-1 md:opacity-95">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/12 text-indigo-300/90 transition-colors group-hover:bg-indigo-500/20 md:h-12 md:w-12">
                    <svg class="h-5 w-5 md:h-6 md:w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div class="text-sm font-bold text-white">Всё связано</div>
                <div class="text-sm leading-relaxed text-slate-400">Сайт, заявки и бронь — не три разных острова.</div>
            </div>
            <div class="group order-1 flex min-h-full flex-col items-center gap-3 rounded-3xl border-2 border-indigo-400/50 bg-gradient-to-b from-indigo-500/25 via-white/[0.12] to-emerald-500/15 px-6 py-8 text-center shadow-lg shadow-indigo-950/40 ring-2 ring-indigo-400/35 transition-transform hover:scale-[1.01] sm:px-8 sm:py-9 md:order-2 md:z-10 md:-my-1 md:scale-[1.03]">
                <span class="rounded-full bg-indigo-500/30 px-3 py-0.5 text-[10px] font-bold uppercase tracking-widest text-indigo-100">Суть RentBase</span>
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-white shadow-inner ring-1 ring-white/20 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div class="text-base font-extrabold text-white">Под реальную работу</div>
                <div class="text-sm leading-relaxed text-indigo-50/95">Мы заранее продумали те ситуации, в которых вы оказываетесь каждый день.</div>
            </div>
            <div class="group order-3 flex min-h-full flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-5 py-6 text-center transition-all hover:bg-white/[0.06] sm:px-6 sm:py-7 md:opacity-95">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/12 text-violet-300/90 transition-colors group-hover:bg-violet-500/20 md:h-12 md:w-12">
                    <svg class="h-5 w-5 md:h-6 md:w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </div>
                <div class="text-sm font-bold text-white">От первого визита к повторному</div>
                <div class="text-sm leading-relaxed text-slate-400">Клиент уже в базе — проще напомнить и вернуть.</div>
            </div>
        </div>

        <p class="fade-reveal mx-auto mt-6 max-w-2xl text-pretty text-sm leading-relaxed text-slate-300 sm:mt-8 sm:text-base [transition-delay:450ms]">
            {!! $cat['tagline'] ?? '' !!}
        </p>
    </div>
</section>
