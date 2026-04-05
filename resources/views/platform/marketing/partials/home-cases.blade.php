@php
    $casesContactUrl = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
    $casesList = $pm['cases'] ?? [];
    $casesTagline = trim((string) ($pm['cases_tagline'] ?? ''));
    $casesLead = trim((string) ($pm['cases_lead'] ?? ''));
@endphp
<section id="primery" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-slate-50" aria-labelledby="primery-heading">
    <div class="relative z-10 mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <div class="text-center">
            @if(!empty($pm['cases_intro']))
                <p class="mb-4 text-center text-sm text-slate-500">{{ $pm['cases_intro'] }}</p>
            @endif
            <h2 id="primery-heading" class="fade-reveal text-balance text-2xl font-bold leading-tight text-slate-900 sm:text-3xl md:text-4xl">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $pm['cases_heading'] ?? 'Как это работает в реальном бизнесе') !!}</h2>
            @if($casesLead !== '')
                <p class="fade-reveal mx-auto mt-3 max-w-2xl text-sm font-semibold text-slate-800 sm:text-base" style="transition-delay: 80ms;">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $casesLead) !!}</p>
            @endif
            <p class="fade-reveal mx-auto mt-3 max-w-2xl text-pretty text-base leading-relaxed text-slate-600 sm:text-lg" style="transition-delay: 100ms;">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['cases_sub'] ?? '') !!}</p>
        </div>

        <div class="mt-10 grid gap-5 sm:mt-12 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
            @foreach($casesList as $index => $case)
                @php
                    $caseIsLink = !empty($case['url']) && !empty($case['real']);
                    $subtitle = $case['subtitle'] ?? $case['type'] ?? '';
                    $bullets = $case['bullets'] ?? $case['stats'] ?? [];
                    $footer = $case['footer'] ?? '';
                    $iconKey = $case['icon'] ?? '';
                @endphp
                <article @class([
                    'group fade-reveal pm-reveal-cases-' . min($index, 4) => true,
                    'relative flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-[transform,box-shadow,border-color] duration-300 sm:p-6',
                    'hover:-translate-y-1 hover:shadow-md' => true,
                    'cursor-pointer hover:border-pm-accent/30 focus-within:-translate-y-1 focus-within:border-pm-accent/30 focus-within:shadow-md' => $caseIsLink,
                    'cursor-default' => ! $caseIsLink,
                ])>
                    @if($caseIsLink)
                        <a
                            href="{{ $case['url'] }}"
                            class="absolute inset-0 z-10 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-pm-accent focus-visible:ring-offset-2"
                            rel="noopener noreferrer"
                            target="_blank"
                            aria-label="Открыть сайт «{{ $case['title'] }}» в новой вкладке"
                            data-pm-event="case_open"
                            data-pm-case="{{ e($case['title'] ?? '') }}"
                        ></a>
                    @endif

                    <div class="relative z-[5] flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 transition-colors group-hover:bg-indigo-100/80" aria-hidden="true">
                            @switch($iconKey)
                                @case('moto')
                                    {{-- Прокат / техника: двухколёсный силуэт (outline, как в наборах Lucide/Hero) --}}
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="5.5" cy="17.5" r="3.25"/>
                                        <circle cx="18" cy="17.5" r="3.25"/>
                                        <path d="M8.75 17.5h5.25"/>
                                        <path d="M14 17.5l-1.25-6.5-3.25 1.25L8 8.25H5.75"/>
                                        <path d="M12.75 11l3-2.75h3.5L21 11.5V14"/>
                                        <path d="M17.25 11l1.5 3.25"/>
                                    </svg>
                                    @break
                                @case('academic')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                                        <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                        <path d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                                    </svg>
                                    @break
                                @case('services')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        <path d="M9 12h6M9 16h4"/>
                                    </svg>
                                    @break
                                @default
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 6h16M4 12h16M4 18h10"/>
                                    </svg>
                            @endswitch
                        </div>
                        <div class="min-w-0 flex-1 text-left">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-bold text-slate-900 transition-colors group-hover:text-pm-accent sm:text-xl">{{ $case['title'] }}</h3>
                                @if(!empty($case['real']))
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">Реальный проект</span>
                                @endif
                            </div>
                            @if($subtitle !== '')
                                <p class="mt-1 text-sm font-medium text-slate-500">{{ $subtitle }}</p>
                            @endif
                        </div>
                    </div>

                    @if(!empty($bullets) && is_array($bullets))
                        <ul class="relative z-[5] mt-5 space-y-2.5 border-t border-slate-100 pt-5 text-left" aria-label="Возможности в системе">
                            @foreach($bullets as $line)
                                <li class="text-sm leading-snug text-slate-700">{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if($footer !== '')
                        <p class="relative z-[5] mt-5 border-t border-slate-100 pt-4 text-sm font-semibold leading-snug text-slate-800">{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $footer) !!}</p>
                    @endif

                    @if($caseIsLink)
                        <div class="min-h-2 flex-1" aria-hidden="true"></div>
                        <p class="pointer-events-none relative z-[11] pt-3 text-sm font-bold text-pm-accent opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-within:opacity-100">
                            Открыть сайт →
                        </p>
                    @endif
                </article>
            @endforeach
        </div>

        @if($casesTagline !== '')
            <p class="fade-reveal mx-auto mt-10 max-w-2xl text-center text-balance text-base font-bold leading-snug text-slate-900 sm:mt-12 sm:text-lg" style="transition-delay: 320ms;">
                {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $casesTagline) !!}
            </p>
        @endif

        <div class="fade-reveal mt-10 flex flex-col items-center gap-4 border-t border-slate-200 pt-10 text-center sm:mt-12 sm:pt-12" style="transition-delay: 400ms;">
            <p class="max-w-xl text-base font-semibold text-slate-800">Ваш проект может быть следующим</p>
            <p class="max-w-xl text-pretty text-base leading-relaxed text-slate-600">Готовы к&nbsp;такому же контуру заявок и&nbsp;операций&nbsp;— с&nbsp;рабочей системой, а&nbsp;не картинками в&nbsp;портфолио.</p>
            <a href="{{ $casesContactUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-pm-accent px-8 py-3 text-base font-bold text-white shadow-premium transition-all hover:-translate-y-0.5 hover:bg-pm-accent-hover" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="cases_footer">
                Запустить свой проект
            </a>
        </div>
    </div>
</section>
