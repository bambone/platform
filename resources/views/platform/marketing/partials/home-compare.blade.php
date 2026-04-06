@php
    $cmp = $pm['compare_table'] ?? [];
@endphp
<section id="sravnenie" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-white" aria-labelledby="sravnenie-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <h2 id="sravnenie-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">
            {!! $cmp['headline'] ?? '' !!}
        </h2>
        <p class="fade-reveal pm-section-lead max-w-2xl text-lg leading-relaxed text-slate-600 sm:text-xl [transition-delay:100ms]">
            {!! $cmp['subline'] ?? '' !!}
        </p>

        <div class="fade-reveal mx-auto mt-10 max-w-5xl -mx-4 px-4 [transition-delay:200ms] md:mx-auto md:mt-12 md:px-0">
            {{-- Мобильная версия: без горизонтального скролла, полный текст --}}
            <ul class="space-y-3 md:hidden" role="list">
                @foreach($cmp['rows'] ?? [] as $row)
                    <li class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100">
                        <p class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-sm font-extrabold text-slate-900">{{ $row['label'] }}</p>
                        <div class="grid grid-cols-1 divide-y divide-slate-100">
                            <div class="bg-gradient-to-r from-indigo-50/95 to-emerald-50/80 px-4 py-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">RentBase</p>
                                <p class="mt-1 text-sm font-bold leading-snug text-emerald-800">{{ $row['rentbase'] }}</p>
                            </div>
                            <div class="bg-slate-100/80 px-4 py-3 opacity-[0.92]">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Другие</p>
                                <p class="mt-1 text-sm font-semibold leading-snug text-slate-600">{{ $row['others'] }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="hidden md:block md:overflow-x-auto md:overscroll-x-contain">
                <table class="w-full min-w-0 table-fixed border-separate border-spacing-0 text-left">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="sticky left-0 z-20 w-[34%] bg-white py-4 pr-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:py-5 sm:text-sm">Параметр</th>
                            <th class="w-[33%] bg-gradient-to-b from-indigo-50 to-emerald-50/90 py-4 pl-2 text-center text-sm font-extrabold text-indigo-800 sm:py-5 sm:text-base">RentBase</th>
                            <th class="w-[33%] bg-slate-100/70 py-4 pl-2 text-center text-sm font-bold text-slate-500 sm:py-5 sm:text-base">Другие</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-700 sm:text-base">
                        @foreach($cmp['rows'] ?? [] as $row)
                        <tr class="align-top">
                            <td class="sticky left-0 z-10 border-b border-slate-100 bg-white py-4 pr-3 font-semibold text-slate-900 sm:py-5">
                                <span class="text-pretty">{{ $row['label'] }}</span>
                            </td>
                            <td class="border-b border-slate-100 bg-indigo-50/50 py-4 pl-2 text-center text-sm font-bold text-emerald-800 sm:py-5">
                                <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-pretty">
                                    {{ $row['rentbase'] }}
                                </div>
                            </td>
                            <td class="border-b border-slate-100 bg-slate-50/90 py-4 pl-2 text-center text-sm font-semibold text-slate-500 sm:py-5">
                                <span class="text-pretty">{{ $row['others'] }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <p class="fade-reveal mt-10 text-balance text-center text-lg font-extrabold text-slate-900 sm:text-xl" style="transition-delay: 250ms;">
            Другие инструменты решают задачи. <span class="text-pm-accent">RentBase управляет бизнесом.</span>
        </p>

        <div class="fade-reveal mt-10 flex flex-col items-stretch justify-center gap-4 rounded-2xl border border-indigo-100 bg-indigo-50/80 p-6 sm:mt-12 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-8" style="transition-delay: 300ms;">
            <p class="text-balance text-center text-lg font-bold text-slate-900 sm:text-left sm:text-xl">
                Готовы заменить 4–7 инструментов на&nbsp;один?
            </p>
            <a href="{{ platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-pm-accent px-6 py-3 text-center font-extrabold text-white shadow-lg transition-transform hover:bg-pm-accent-hover active:scale-95">
                Начать работу
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </a>
        </div>
    </div>
</section>
