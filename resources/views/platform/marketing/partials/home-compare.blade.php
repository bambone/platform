@php
    $cmp = $pm['compare_table'] ?? [];
@endphp
<section id="sravnenie" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-white" aria-labelledby="sravnenie-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <h2 id="sravnenie-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">
            {!! $cmp['headline'] ?? '' !!}
        </h2>
        <p class="fade-reveal mt-6 max-w-2xl text-lg leading-relaxed text-slate-600 sm:text-xl" style="transition-delay: 100ms;">
            {!! $cmp['subline'] ?? '' !!}
        </p>

        <div class="fade-reveal mx-auto mt-10 max-w-5xl -mx-4 overflow-x-auto overscroll-x-contain px-4 md:mx-auto md:mt-12 md:px-0" style="transition-delay: 200ms;">
            <table class="w-full min-w-[36rem] border-separate border-spacing-0 text-left lg:min-w-0">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="sticky left-0 z-20 w-[38%] bg-white py-4 pr-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:py-5 sm:text-sm">Параметр</th>
                        <th class="w-[31%] py-4 text-center text-sm font-extrabold text-indigo-700 sm:py-5 sm:text-base">RentBase</th>
                        <th class="w-[31%] py-4 text-center text-sm font-bold text-slate-600 sm:py-5 sm:text-base">Другие сервисы</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-700 sm:text-base">
                    @foreach($cmp['rows'] ?? [] as $row)
                    <tr>
                        <td class="sticky left-0 z-10 border-b border-slate-100 bg-white py-4 pr-3 font-semibold text-slate-900 sm:py-5">
                            {{ $row['label'] }}
                        </td>
                        <td class="border-b border-slate-100 py-4 text-center font-semibold text-emerald-700 sm:py-5">
                            <div class="flex items-center justify-center gap-2">
                                {{ $row['rentbase'] }}
                            </div>
                        </td>
                        <td class="border-b border-slate-100 py-4 text-center font-medium text-slate-600 sm:py-5">
                            {{ $row['others'] }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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
