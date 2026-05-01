@php
    $casesContactUrl = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
    $casesList = array_values(array_filter($pm['cases'] ?? [], static fn ($c): bool => is_array($c)));
    $casesTagline = trim((string) ($pm['cases_tagline'] ?? ''));
    $casesLead = trim((string) ($pm['cases_lead'] ?? ''));

    $casesTotalCount = count($casesList);

    /*
     | Spotlight (максимум 2): явное featured или первые кейсы до двух при отсутствии флагов.
     | cases_compact_limit обрезает только компактную сетку; счётчики фильтров ниже считают по карточкам,
     | реально попавшим в DOM (spotlight + компакт).
    */
    $explicitSpotIdx = [];
    foreach ($casesList as $idx => $caseRow) {
        if (! empty($caseRow['featured'])) {
            $explicitSpotIdx[] = (int) $idx;
        }
    }
    if ($explicitSpotIdx === []) {
        for ($s = 0; $s < min(2, $casesTotalCount); $s++) {
            $explicitSpotIdx[] = $s;
        }
    }
    $spotIdx = array_slice($explicitSpotIdx, 0, 2);

    $casesFeatured = [];
    $casesCompact = [];
    foreach ($casesList as $idx => $caseRow) {
        if (in_array($idx, $spotIdx, true)) {
            $casesFeatured[] = $caseRow;
        } else {
            $casesCompact[] = $caseRow;
        }
    }

    $compactLimit = $pm['cases_compact_limit'] ?? null;
    if (is_int($compactLimit) && $compactLimit > 0) {
        $casesCompact = array_slice($casesCompact, 0, $compactLimit);
    }

    $countsByCat = [];
    foreach (array_merge($casesFeatured, $casesCompact) as $caseRow) {
        $kc = strtolower(trim((string) ($caseRow['category'] ?? 'other')));
        if ($kc === '') {
            $kc = 'other';
        }
        $countsByCat[$kc] = ($countsByCat[$kc] ?? 0) + 1;
    }
    $casesVisibleCount = count($casesFeatured) + count($casesCompact);

    $filterDefs = is_array($pm['cases_filters'] ?? null)
        ? ($pm['cases_filters'])
        : [
            ['id' => 'all', 'label' => 'Все'],
            ['id' => 'rent', 'label' => 'Прокат'],
            ['id' => 'detailing', 'label' => 'Детейлинг'],
            ['id' => 'education', 'label' => 'Обучение'],
            ['id' => 'services', 'label' => 'Услуги'],
            ['id' => 'legal', 'label' => 'Юридические услуги'],
            ['id' => 'auto', 'label' => 'Авто'],
            ['id' => 'other', 'label' => 'Другое'],
        ];
    $showFilterCounts = (bool) ($pm['cases_show_filter_counts'] ?? true);
    $caseFilters = [];
    $allLabel = 'Все';
    if ($showFilterCounts && $casesVisibleCount > 0) {
        $allLabel .= ' · ' . $casesVisibleCount;
    }
    $caseFilters[] = ['id' => 'all', 'label' => $allLabel];
    foreach ($filterDefs as $fd) {
        $fid = isset($fd['id']) ? (string) $fd['id'] : '';
        if ($fid === '' || $fid === 'all') {
            continue;
        }
        $n = $countsByCat[$fid] ?? 0;
        if ($n < 1) {
            continue;
        }
        $flab = (string) ($fd['label'] ?? $fid);
        if ($showFilterCounts) {
            $flab .= ' · ' . $n;
        }
        $caseFilters[] = ['id' => $fid, 'label' => $flab];
    }

    $midCta = is_array($pm['cases_mid_cta'] ?? null) ? ($pm['cases_mid_cta']) : [];
    $midHrefRaw = isset($midCta['button_href']) ? trim((string) $midCta['button_href']) : '';
    $midCtaButtonHref = $midHrefRaw !== '' ? $midHrefRaw : $casesContactUrl;
    $trustBusinesses = trim((string) (($pm['trust'] ?? [])['businesses'] ?? '30+'));
    $midCtaNoteText = isset($midCta['note']) ? (string) $midCta['note'] : '';
    /** @var list<string>|array{0: string, 1?: string} Разбиение по маркеру :businesses */
    $midCtaNoteParts = $midCtaNoteText !== '' && str_contains($midCtaNoteText, ':businesses')
        ? explode(':businesses', $midCtaNoteText, 2)
        : [$midCtaNoteText];
    $hasMidHeadline = ! empty($midCta['headline']);

    $pillTone = fn (string $cat): string => match ($cat) {
        'rent', 'auto' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/15',
        'education' => 'bg-amber-50 text-amber-900 ring-amber-600/15',
        'legal', 'services' => 'bg-indigo-50 text-indigo-900 ring-indigo-600/15',
        'detailing' => 'bg-sky-50 text-sky-900 ring-sky-600/15',
        default => 'bg-slate-100 text-slate-800 ring-slate-500/15',
    };

    $featuredMediaTone = fn (string $cat): string => match ($cat) {
        'rent', 'auto' => 'from-emerald-900/95 via-slate-900 to-slate-950',
        'education' => 'from-amber-900/85 via-slate-900 to-slate-950',
        'legal', 'services' => 'from-indigo-950 via-slate-900 to-slate-950',
        'detailing' => 'from-cyan-950 via-slate-900 to-slate-950',
        default => 'from-slate-800 via-indigo-950 to-slate-950',
    };
@endphp
<section id="primery" class="pm-section-anchor pm-section-y border-b border-slate-200 bg-slate-50" aria-labelledby="primery-heading">
    <div class="relative z-10 mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <div class="text-center">
            @if(!empty($pm['cases_intro']))
                <p class="mb-4 text-center text-sm text-slate-500">{{ $pm['cases_intro'] }}</p>
            @endif
            <h2 id="primery-heading" class="fade-reveal text-balance text-2xl font-bold leading-tight text-slate-900 sm:text-3xl md:text-4xl">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $pm['cases_heading'] ?? 'Как RentBase работает в реальном бизнесе') !!}</h2>
            @if($casesLead !== '')
                <p class="fade-reveal mx-auto mt-3 max-w-2xl text-sm font-semibold text-slate-800 sm:text-base [transition-delay:80ms]">{!! str_replace([' для ', ' с ', ' в ', ' и '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'], $casesLead) !!}</p>
            @endif
            <p class="fade-reveal mx-auto mt-3 max-w-3xl text-pretty text-base leading-relaxed text-slate-600 sm:text-lg [transition-delay:100ms]">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $pm['cases_sub'] ?? '') !!}</p>
        </div>

        @if(count($caseFilters))
            <div data-pm-cases-toolbar class="pm-section-lead flex justify-center fade-reveal [transition-delay:120ms]" role="toolbar" aria-label="Фильтр кейсов по нише">
                <div class="-mx-1 flex max-w-full flex-wrap items-center justify-center gap-2 px-1 sm:flex-nowrap sm:justify-center md:flex-wrap lg:flex-nowrap" aria-label="Категории кейсов">
                    @foreach($caseFilters as $fi => $filt)
                        @php
                            $fid = isset($filt['id']) ? (string) $filt['id'] : 'filter-' . $fi;
                            $lab = isset($filt['label']) ? (string) $filt['label'] : $fid;
                        @endphp
                        <button
                            type="button"
                            id="pm-case-filter-{{ $fid }}"
                            data-pm-case-filter="{{ e($fid) }}"
                            aria-pressed="{{ $fid === 'all' ? 'true' : 'false' }}"
                            class="pm-case-tab px-3.5 md:px-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pm-accent focus-visible:ring-offset-2"
                            data-pm-location="cases_filter"
                            data-pm-filter="{{ e($fid) }}"
                        >
                            {{ $lab }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div
            id="pm-cases-featured"
            data-pm-cases-featured-wrap=""
            class="{{ count($casesFeatured) ? '' : 'hidden' }} pm-cases-featured pm-section-lead mx-auto mt-10 max-w-6xl grid grid-cols-1 items-start gap-6 lg:grid-cols-2 lg:gap-6 xl:gap-8"
        >
            @foreach($casesFeatured as $fi => $case)
                @php
                    $caseIsLink = !empty($case['url']) && !empty($case['real']);
                    $subtitle = $case['subtitle'] ?? $case['type'] ?? '';
                    $badgeText = isset($case['badge']) ? (string) $case['badge'] : '';
                    $badgeText = trim($badgeText !== '' ? $badgeText : (string) $subtitle);
                    $bullets = $case['bullets'] ?? $case['stats'] ?? [];
                    $footer = $case['footer'] ?? '';
                    $iconKey = $case['icon'] ?? '';
                    $metrics = trim((string) ($case['metrics'] ?? ''));
                    $category = strtolower(trim((string) ($case['category'] ?? 'other')));
                    $statsStrip = $case['stats_strip'] ?? null;
                    if (! is_array($statsStrip)) {
                        $statsStrip = [];
                    }
                    $featImg = isset($case['featured_image']) ? trim((string) $case['featured_image']) : '';
                    $featPosRaw = trim((string) ($case['featured_image_position'] ?? ''));
                    $featPosCss = ($featPosRaw !== '' && strlen($featPosRaw) <= 80 && preg_match('/^[-0-9a-zA-Z%+._\s\\/()]+$/', $featPosRaw) === 1)
                        ? $featPosRaw
                        : 'center';
                    $mediaGrad = ($featuredMediaTone)($category);
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
                @endphp
                <article
                    id="case-featured-{{ Str::slug($case['title'] ?? ('case-' . $fi)) }}-{{ $fi }}"
                    data-pm-case-cat="{{ e($category) }}"
                    class="group fade-reveal pm-reveal-cases-{{ min($fi, 4) }} relative self-start overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-[transform,box-shadow,border-color] duration-300 lg:hover:-translate-y-px lg:hover:border-slate-300 lg:hover:shadow-md {{ $caseIsLink ? 'cursor-pointer' : 'cursor-default' }}"
                >
                    @if($caseIsLink)
                        <a
                            href="{{ $case['url'] }}"
                            class="absolute inset-0 z-20 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-pm-accent"
                            rel="noopener noreferrer"
                            target="_blank"
                            aria-label="Открыть сайт «{{ $case['title'] }}» в новой вкладке"
                            data-pm-event="case_open"
                            data-pm-case="{{ e($case['title'] ?? '') }}"
                        ></a>
                    @endif

                    <div class="relative z-10 grid grid-cols-1 items-start gap-6 p-5 sm:p-6 xl:grid-cols-2 xl:gap-10 xl:p-8">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-start gap-4">
                                @if($initials !== '')
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-800 to-slate-950 text-base font-black text-white shadow-md ring-2 ring-white" aria-hidden="true">{{ $initials }}</div>
                                @else
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-700 ring-1 ring-slate-200" aria-hidden="true">
                                        @include('platform.marketing.partials.case-study-icon', ['icon' => $iconKey, 'svg_class' => 'h-7 w-7'])
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 gap-y-2">
                                        <h3 class="text-xl font-bold text-slate-900 transition-colors sm:text-2xl">{{ $case['title'] }}</h3>
                                        @if($badgeText !== '')
                                            <span class="inline-flex shrink-0 items-center rounded-full px-3 py-0.5 text-xs font-bold ring-1 {{ ($pillTone)($category) }}">{{ $badgeText }}</span>
                                        @endif
                                    </div>
                                    @if($metrics !== '')
                                        <p class="mt-2 text-[0.6875rem] font-semibold uppercase tracking-wide text-slate-400 sm:text-xs">{{ $metrics }}</p>
                                    @endif
                                    @if($caseIsLink)
                                        <span class="mt-3 inline-flex text-sm font-bold text-pm-accent pointer-events-none" aria-hidden="true">Открыть сайт →</span>
                                    @endif
                                </div>
                            </div>

                            @if(! empty($bullets) && is_array($bullets))
                                <ul class="mt-6 space-y-3 text-left" aria-label="Возможности в системе">
                                    @foreach(array_slice($bullets, 0, 3) as $line)
                                        <li class="flex gap-3 text-sm leading-snug text-slate-700 sm:text-[0.9375rem]">
                                            <span class="mt-2 inline-block h-1 w-4 shrink-0 rounded-full bg-pm-accent opacity-90" aria-hidden="true"></span>
                                            <span>{{ $line }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if($footer !== '')
                                <p class="relative mt-6 border-t border-slate-100 pt-6 text-sm font-semibold leading-snug text-slate-800 sm:text-base">{{ $footer }}</p>
                            @endif
                        </div>

                        <div class="relative z-10 flex min-h-[180px] items-stretch sm:min-h-[200px] xl:min-h-[260px]">
                            @if($featImg !== '')
                                <figure
                                    class="relative w-full overflow-hidden rounded-2xl ring-1 ring-slate-200/80"
                                    {{ new \Illuminate\View\ComponentAttributeBag(['style' => '--pm-case-op: '.$featPosCss]) }}
                                >
                                    <img src="{{ $featImg }}" alt="Снимок главной страницы: {{ strip_tags(trim((string) ($case['title'] ?? ''))) }}" class="h-full max-h-80 w-full object-cover lg:max-h-none" style="object-position: var(--pm-case-op)" loading="lazy" decoding="async" />
                                    <figcaption class="sr-only">{{ $case['title'] ?? '' }}</figcaption>
                                </figure>
                            @else
                                <div class="{{ 'relative flex w-full items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br shadow-inner ring-1 ring-slate-200/70 ' . $mediaGrad }}" aria-hidden="true">
                                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.12),transparent_55%)]"></div>
                                    <div class="absolute inset-x-8 bottom-0 h-24 bg-gradient-to-t from-black/30 to-transparent"></div>
                                    <div class="relative text-white/90 opacity-95">
                                        @include('platform.marketing.partials.case-study-icon', ['icon' => $iconKey, 'svg_class' => 'h-28 w-28 drop-shadow-xl sm:h-36 sm:w-36'])
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if(count($statsStrip))
                        <div class="relative z-10 grid grid-cols-1 divide-y divide-slate-200 border-t border-slate-100 bg-slate-50/90 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                            @foreach($statsStrip as $cell)
                                @php
                                    $v = isset($cell['value']) ? (string) $cell['value'] : '';
                                    $l = isset($cell['label']) ? (string) $cell['label'] : '';
                                    if ($v === '' && $l === '') {
                                        continue;
                                    }
                                @endphp
                                <div class="flex gap-4 px-5 py-4 sm:flex-col sm:px-6 sm:py-5 lg:gap-2">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-14h8V3h-8v4z"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1 sm:flex-none">
                                        <p class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ $v }}</p>
                                        <p class="mt-1 text-sm font-medium leading-snug text-slate-600">{{ $l }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div id="pm-cases-grid" class="{{ count($casesCompact) ? '' : 'hidden' }} pm-cases-compact pm-section-lead mx-auto mt-10 flex max-w-6xl flex-wrap justify-center gap-5 lg:gap-6">
            @foreach($casesCompact as $ci => $case)
                @php
                    $caseIsLink = !empty($case['url']) && !empty($case['real']);
                    $subtitle = $case['subtitle'] ?? $case['type'] ?? '';
                    $badgeText = isset($case['badge']) ? trim((string) $case['badge']) : '';
                    $badgeText = $badgeText !== '' ? $badgeText : trim((string) $subtitle);
                    $bullets = $case['bullets'] ?? $case['stats'] ?? [];
                    $footer = $case['footer'] ?? '';
                    $iconKey = $case['icon'] ?? '';
                    $metrics = trim((string) ($case['metrics'] ?? ''));
                    $category = strtolower(trim((string) ($case['category'] ?? 'other')));
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
                @endphp
                <article
                    id="case-compact-{{ Str::slug($case['title'] ?? ('case-' . $ci)) }}-{{ $ci }}"
                    data-pm-case-cat="{{ e($category) }}"
                    class="pm-cases-compact-card group fade-reveal pm-reveal-cases-{{ min($ci + 2, 4) }} relative flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-[transform,box-shadow,border-color] duration-300 hover:-translate-y-1 hover:shadow-md {{ $caseIsLink ? 'cursor-pointer hover:border-pm-accent/35 focus-within:-translate-y-1 focus-within:border-pm-accent/35 focus-within:shadow-md' : 'cursor-default' }}"
                >
                    @if($caseIsLink)
                        <a href="{{ $case['url'] }}" class="absolute inset-0 z-10 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-pm-accent focus-visible:ring-offset-2" rel="noopener noreferrer" target="_blank" aria-label="Открыть сайт «{{ $case['title'] }}» в новой вкладке" data-pm-event="case_open" data-pm-case="{{ e($case['title'] ?? '') }}"></a>
                    @endif

                    <div class="pointer-events-none absolute right-4 top-4 z-[1] opacity-[0.08] text-pm-accent" aria-hidden="true">
                        @include('platform.marketing.partials.case-study-icon', ['icon' => $iconKey, 'svg_class' => 'h-24 w-24 sm:h-28 sm:w-28'])
                    </div>

                    <div class="relative z-[5] flex items-start gap-4">
                        @if($initials !== '')
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-800 to-slate-950 text-sm font-black text-white shadow-md ring-1 ring-white/10" aria-hidden="true">{{ $initials }}</div>
                        @else
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100" aria-hidden="true">
                                @include('platform.marketing.partials.case-study-icon', ['icon' => $iconKey, 'svg_class' => 'h-6 w-6'])
                            </div>
                        @endif
                        <div class="min-w-0 flex-1 text-left">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-bold text-slate-900 transition-colors group-hover:text-pm-accent sm:text-xl">{{ $case['title'] }}</h3>
                            </div>
                            @if($badgeText !== '')
                                <span class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 {{ ($pillTone)($category) }}">{{ $badgeText }}</span>
                            @endif
                            @if(!empty($case['real']))
                                <span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-700/15">Реальный проект</span>
                            @endif
                            @if($subtitle !== '')
                                <p class="mt-2 text-xs font-medium text-slate-500">{{ $subtitle }}</p>
                            @endif
                            @if($metrics !== '')
                                <p class="mt-2 text-xs font-semibold leading-snug text-slate-600">{{ $metrics }}</p>
                            @endif
                        </div>
                    </div>

                    @if(!empty($bullets) && is_array($bullets))
                        <div class="relative z-[5] mt-5 flex flex-1 flex-col border-t border-slate-100 pt-5 text-left">
                            <ul class="space-y-2.5" aria-label="Возможности в системе">
                                @foreach($bullets as $line)
                                    <li class="flex gap-3 text-sm leading-snug text-slate-700">
                                        <span class="mt-2 inline-block h-1 w-3 shrink-0 rounded-full bg-pm-accent/90" aria-hidden="true"></span>
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="flex-1" aria-hidden="true"></div>
                        </div>
                    @endif

                    @if($footer !== '')
                        <p class="relative z-[5] mt-5 border-t border-slate-100 pt-4 text-sm font-semibold leading-snug text-slate-800">{!! str_replace([' для ', ' с ', ' в '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;'], $footer) !!}</p>
                    @endif

                    @if($caseIsLink)
                        <p class="pointer-events-none relative z-[15] mt-4 text-sm font-bold text-pm-accent opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-within:opacity-100">Открыть сайт →</p>
                    @endif
                </article>
            @endforeach
        </div>

        <div
            id="pm-cases-empty"
            class="pm-section-lead mx-auto mt-10 hidden max-w-lg rounded-2xl border border-dashed border-slate-300 bg-slate-50/95 px-5 py-8 text-center sm:mt-12"
            aria-live="polite"
            data-pm-cases-empty=""
            role="status"
        >
            <p class="text-base font-semibold text-slate-800">Под этим фильтром кейсов пока нет.</p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">Выберите «Все» или другую категорию — витрина обновится сразу.</p>
            <button
                type="button"
                id="pm-cases-empty-reset"
                class="mt-5 rounded-full border border-slate-800 bg-white px-6 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-pm-accent focus-visible:ring-offset-2"
                data-pm-case-filter="all"
                data-pm-location="cases_empty_reset"
            >
                Показать все кейсы
            </button>
        </div>

        @if($casesTagline !== '')
            <p class="fade-reveal mx-auto mt-10 max-w-2xl text-center text-balance text-base font-bold leading-snug text-slate-900 sm:mt-12 sm:text-lg [transition-delay:360ms]">
                {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $casesTagline) !!}
            </p>
        @endif

        @if($hasMidHeadline)
            <div class="mx-auto mt-12 max-w-5xl fade-reveal [transition-delay:380ms]" data-pm-cases-mid-cta="">
                <div class="relative overflow-hidden rounded-2xl border border-slate-800/70 bg-gradient-to-br from-slate-900 via-slate-950 to-[#131c3a] px-5 py-6 shadow-lg sm:flex sm:flex-wrap sm:items-center sm:justify-between sm:gap-8 sm:px-8 sm:py-8">
                    <div class="absolute right-6 top-5 h-28 w-28 rounded-full bg-pm-accent/15 blur-2xl" aria-hidden="true"></div>
                    <div class="relative z-[1] max-w-xl">
                        <div class="mb-4 flex items-center gap-3 sm:mb-0">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500/20 ring-1 ring-indigo-400/35" aria-hidden="true">
                                <svg class="h-6 w-6 text-indigo-200" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10l5-3 7 5 5-3V9l-5 3L8 8 3 7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 8v12"/></svg>
                            </div>
                            <div>
                                <p class="text-lg font-bold leading-snug text-white sm:text-xl">{{ $midCta['headline'] }}</p>
                                @if(! empty($midCta['subline']))
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">{{ $midCta['subline'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="relative z-[1] mt-6 flex shrink-0 flex-col items-stretch gap-4 sm:mt-0 sm:items-end sm:text-right">
                        <div class="flex flex-wrap items-center gap-4 sm:flex-nowrap sm:justify-end">
                            <a
                                href="{{ $midCtaButtonHref }}"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-indigo-500 px-6 py-2.5 text-sm font-bold text-white shadow-[0_1px_0_0_rgb(255_255_255/0.1)_inset] ring-1 ring-white/15 transition-colors hover:bg-indigo-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                                data-pm-event="cta_click"
                                data-pm-cta="cases_mid_launch_consult"
                                data-pm-location="cases_mid_banner"
                            >
                                <span>{{ $midCta['button_label'] ?? 'Смотреть все кейсы' }}</span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 12h14"/></svg>
                            </a>
                        </div>
                        @if(trim($midCtaNoteText) !== '')
                            <p class="max-w-[18rem] text-xs font-medium leading-relaxed text-slate-400">
                                @if(isset($midCtaNoteParts[1]))
                                    {{ trim($midCtaNoteParts[0] ?? '') }}<span class="whitespace-nowrap font-bold text-white">{{ $trustBusinesses }}</span>{{ trim((string) $midCtaNoteParts[1]) }}
                                @else
                                    {{ trim($midCtaNoteText) }}
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if(! $hasMidHeadline)
            <div class="fade-reveal mt-10 flex flex-col items-center gap-4 border-t border-slate-200 pt-10 text-center sm:mt-12 sm:pt-12 [transition-delay:400ms]">
                <p class="max-w-xl text-base font-semibold text-slate-800">Ваш проект может быть следующим</p>
                <p class="max-w-xl text-pretty text-base leading-relaxed text-slate-600">
                    Хотите такую же связку сайта и записи с рабочими экранами&nbsp;— без картинки «как мы когда-то сделали».
                </p>
                <a href="{{ $casesContactUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-pm-accent px-8 py-3 text-base font-bold text-white shadow-premium transition-all hover:-translate-y-0.5 hover:bg-pm-accent-hover" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="cases_footer">
                    Хочу обсудить
                </a>
            </div>
        @endif
    </div>
</section>
