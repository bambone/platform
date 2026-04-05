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
        <div class="mb-8 flex justify-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-indigo-300 backdrop-blur-md">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-indigo-400"></span>
                Новая категория
            </span>
        </div>

        <h2 id="category-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-white sm:text-4xl md:text-5xl">
            {!! $cat['headline'] ?? '' !!}
        </h2>

        <p class="fade-reveal mt-8 text-balance text-2xl font-bold text-white sm:text-3xl" style="transition-delay: 200ms;">
            {!! $cat['subline'] ?? '' !!}
        </p>

        <!-- What it IS — three pillars -->
        <div class="fade-reveal mt-10 grid grid-cols-1 gap-4 sm:mt-12 sm:grid-cols-3 sm:gap-5" style="transition-delay: 350ms;">
            <div class="group flex min-h-full flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-6 text-center transition-all hover:bg-white/[0.07] sm:px-5">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-300 transition-colors group-hover:bg-indigo-500/25">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div class="text-sm font-bold text-white">Единый процесс</div>
                <div class="text-sm leading-relaxed text-slate-300">Сайт, заявки, клиенты и&nbsp;бронирования работают как один механизм.</div>
            </div>
            <div class="group flex min-h-full flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-6 text-center transition-all hover:bg-white/[0.07] sm:px-5">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-300 transition-colors group-hover:bg-emerald-500/25">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div class="text-sm font-bold text-white">Готовая логика</div>
                <div class="text-sm leading-relaxed text-slate-300">Бизнес-процессы вшиты в&nbsp;архитектуру, а&nbsp;не&nbsp;собраны на&nbsp;костылях.</div>
            </div>
            <div class="group flex min-h-full flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-6 text-center transition-all hover:bg-white/[0.07] sm:px-5">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/15 text-violet-300 transition-colors group-hover:bg-violet-500/25">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </div>
                <div class="text-sm font-bold text-white">Полный цикл</div>
                <div class="text-sm leading-relaxed text-slate-300">От&nbsp;первого клика до&nbsp;повторной продажи&nbsp;— без&nbsp;сторонних сервисов.</div>
            </div>
        </div>

        <p class="fade-reveal mx-auto mt-8 max-w-2xl text-pretty text-base leading-relaxed text-slate-300 sm:mt-10" style="transition-delay: 450ms;">
            {!! $cat['tagline'] ?? '' !!}
        </p>
    </div>
</section>
