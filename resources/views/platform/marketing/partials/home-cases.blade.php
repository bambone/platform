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

        <div class="mt-6 grid grid-cols-1 gap-4 md:mt-8 md:grid-cols-2 md:gap-5 xl:grid-cols-4">
            @foreach($casesList as $index => $case)
                @php
                    $caseIsLink = !empty($case['url']) && !empty($case['real']);
                    $subtitle = $case['subtitle'] ?? $case['type'] ?? '';
                    $bullets = $case['bullets'] ?? $case['stats'] ?? [];
                    $footer = $case['footer'] ?? '';
                    $iconKey = $case['icon'] ?? '';
                    $metrics = trim((string) ($case['metrics'] ?? ''));
                    $initials = '';
                    if (! empty($case['real'])) {
                        $titleForInitials = trim((string) ($case['title'] ?? ''));
                        if ($titleForInitials !== '') {
                            $parts = preg_split('/\s+/u', $titleForInitials, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
                            if (isset($parts[1])) {
                                $initials .= mb_strtoupper(mb_substr($parts[1], 0, 1));
                            }
                        }
                    }
                    $iconWrapClass = match ($iconKey) {
                        'driving' => 'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-50 via-white to-orange-50 text-amber-900 shadow-sm ring-1 ring-amber-200/80 transition-colors group-hover:from-amber-100 group-hover:to-orange-50/90',
                        'legal' => 'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 via-white to-sky-50 text-slate-800 shadow-sm ring-1 ring-slate-200/90 transition-colors group-hover:from-slate-50 group-hover:to-sky-50/80',
                        'detailing' => 'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-800 via-slate-900 to-cyan-950 text-cyan-200 shadow-sm ring-1 ring-cyan-500/20 transition-colors group-hover:from-slate-700 group-hover:via-slate-800 group-hover:to-cyan-900',
                        default => 'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 transition-colors group-hover:bg-indigo-100/80',
                    };
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
                        @if($initials !== '')
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-800 to-slate-900 text-sm font-black text-white shadow-md ring-1 ring-white/10" aria-hidden="true">{{ $initials }}</div>
                        @endif
                        <div @class([
                            $iconWrapClass,
                            'hidden' => $initials !== '',
                        ]) aria-hidden="true">
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
                                @case('driving')
                                    {{-- Инструктор / автошкола: руль + дорожная разметка --}}
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="13" r="5.25"/>
                                        <circle cx="12" cy="13" r="1.75"/>
                                        <path d="M12 7.75V5.5M8.2 9.1 6.9 7.1M15.8 9.1 17.1 7.1"/>
                                        <path d="M5 21h14" stroke-width="1.5" opacity="0.45"/>
                                        <path d="M8 21v-1.5M12 21v-2M16 21v-1.5" stroke-width="1.25" opacity="0.35"/>
                                    </svg>
                                    @break
                                @case('detailing')
                                    {{-- Детейлинг: силуэт кузова + блик --}}
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 16.5V14l1.2-2.2a2.2 2.2 0 0 1 2-1.3h11.6a2.2 2.2 0 0 1 2 1.3L20 14v2.5" opacity="0.9"/>
                                        <path d="M3.5 16.5h17" />
                                        <circle cx="7.5" cy="16.5" r="1.6"/>
                                        <circle cx="16.5" cy="16.5" r="1.6"/>
                                        <path d="M5.2 10.5h2.1l1.4-2.3h6.6l1.4 2.3h2.1" />
                                        <path d="M9 8.2c1.2-1.1 2.6-1.5 3-1.5h.2c.4 0 1.8.4 3 1.5" opacity="0.7"/>
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
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">Пример сценария</span>
                                @endif
                            </div>
                            @if($subtitle !== '')
                                <p class="mt-1 text-sm font-medium text-slate-500">{{ $subtitle }}</p>
                            @endif
                            @if($metrics !== '')
                                <p class="mt-2 text-xs font-semibold leading-snug text-slate-600">{{ $metrics }}</p>
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
