@php
    $nic = $pm['niches'] ?? [];
@endphp
<section id="niches" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-white" aria-labelledby="niches-heading">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <div class="text-center">
            <h2 id="niches-heading" class="fade-reveal text-balance text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl">
                {!! $nic['headline'] ?? '' !!}
            </h2>
            <p class="fade-reveal pm-section-lead text-balance text-base leading-relaxed text-slate-700 sm:text-lg [transition-delay:100ms]">
                {!! $nic['subline'] ?? '' !!}
            </p>
        </div>

        <div class="fade-reveal mt-6 grid grid-cols-1 gap-4 md:mt-8 md:grid-cols-2 md:gap-6 lg:grid-cols-3 [transition-delay:200ms]">
            @foreach($nic['items'] ?? [] as $i => $item)
                <div class="group relative flex h-full flex-col rounded-3xl border border-slate-200 p-7 transition-all hover:border-indigo-200 hover:bg-slate-50/50 hover:shadow-xl sm:p-8">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm transition-transform group-hover:scale-105 group-hover:bg-indigo-600 group-hover:text-white">
                        @if(($item['icon'] ?? '') === 'truck')
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                        @elseif(($item['icon'] ?? '') === 'academic-cap')
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
                        @else
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        @endif
                    </div>
                    <h3 class="text-lg font-extrabold leading-snug text-slate-900 sm:text-xl">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-xs font-bold uppercase tracking-wide text-indigo-600">{{ $item['description'] }}</p>
                    <p class="mt-3 text-sm leading-relaxed text-slate-700 sm:text-base">Инструменты под специфику {{ mb_strtolower($item['title']) }} — без самосборки.</p>
                </div>
            @endforeach
        </div>

        <div class="fade-reveal mt-8 text-center sm:mt-10 [transition-delay:500ms]">
            <p class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base font-bold text-slate-900 sm:px-8 sm:py-5 sm:text-lg">
                Если у&nbsp;вас есть клиенты и&nbsp;расписание&nbsp;— вы&nbsp;наш клиент.
            </p>
        </div>
    </div>
</section>
