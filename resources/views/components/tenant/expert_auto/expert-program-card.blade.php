@props([
    'program',
    'tenant',
    'articleStyle' => null,
    'forcedPictureMode' => 'auto',
    'programIndex' => 0,
    'spanFeaturedInGrid' => false,
    'isPreview' => false,
    /** Превью обложки: style с корня `serviceProgramCoverFocalEditor` — сразу при движении, без ожидания Livewire. */
    'focalStyleFromAlpine' => false,
    /** mobile | tablet | desktop — какие focal-строки подставлять в --svc-* (см. programCardArticleStyleForPreview). */
    'focalPreviewMode' => null,
    'bindProgramsMore' => false,
    'ctaMode' => null,
    'enrollmentUrl' => null,
    'enrollmentPageSlug' => null,
    'enrollmentConfig' => null,
])
@php
    $forcedMode = (string) $forcedPictureMode;
    if (! in_array($forcedMode, ['auto', 'mobile', 'desktop'], true)) {
        $forcedMode = 'auto';
    }
    $pi = (int) $programIndex;
    $spanFeatured = (bool) $spanFeaturedInGrid;
    $linesFromJson = static function ($json): array {
        if (! is_array($json)) {
            return [];
        }
        $out = [];
        foreach ($json as $x) {
            if (is_string($x) && trim($x) !== '') {
                $out[] = trim($x);
            } elseif (is_array($x) && filled($x['text'] ?? '')) {
                $out[] = trim((string) $x['text']);
            }
        }
        return $out;
    };
    $audience = $linesFromJson($program->audience_json);
    $outcomes = $linesFromJson($program->outcomes_json);
    $audienceHead = array_slice($audience, 0, 4);
    $audienceMore = array_slice($audience, 4);
    $outcomesHead = array_slice($outcomes, 0, 4);
    $outcomesMore = array_slice($outcomes, 4);
    $formatParts = array_filter([$program->format_label, $program->duration_label]);
    $formatLine = implode(' · ', $formatParts);
    $price = $program->formattedPriceLabel($tenant);
    $desktopUrl = $program->coverDesktopPublicUrl($tenant);
    $mobileUrl = $program->coverMobilePublicUrl($tenant);
    $coverAlt = $program->coverImageAlt();
    $hasPaneMedia = filled($desktopUrl);
    $useMobileSource = $hasPaneMedia && $mobileUrl !== $desktopUrl;
    if ($articleStyle === null || $articleStyle === false) {
        $articleStyle = $hasPaneMedia
            ? app(\App\MediaPresentation\ServiceProgramCardPresentationResolver::class)->articleStyleAttribute($program)
            : '';
    } elseif (! is_string($articleStyle)) {
        $articleStyle = (string) $articleStyle;
    }
    $articleStyleAttr = $articleStyle !== '' ? ' style="'.e($articleStyle).'"' : '';
    $enrollmentConfig = $enrollmentConfig ?? \App\Tenant\Expert\TenantEnrollmentCtaConfig::forCurrent();
    $resolvedCtaMode = $ctaMode ?? ($enrollmentConfig?->mode() ?? \App\Tenant\Expert\TenantEnrollmentCtaConfig::MODE_MODAL);
    if ($enrollmentUrl === null) {
        $enrollmentSlug = trim((string) ($enrollmentPageSlug ?? ($enrollmentConfig?->enrollmentPageSlug() ?? 'programs'))) ?: 'programs';
        $enrollmentUrl = url('/'.$enrollmentSlug);
    }
    /** WYSIWYG desktop preview: в админке ширина карточки &lt; 64rem, иначе @container не включает «широкие» --svc *-desktop. */
    $wysiwygAsDesktop = $isPreview
        && $focalStyleFromAlpine
        && filled($focalPreviewMode)
        && (string) $focalPreviewMode === 'desktop';
@endphp
<article
    class="expert-program-card group/card relative flex min-w-0 flex-col overflow-hidden rounded-[1.35rem] border transition-transform duration-300 sm:rounded-[1.5rem] sm:hover:-translate-y-1 {{ $spanFeatured ? 'expert-program-card--featured border-white/[0.12] shadow-[0_20px_48px_-20px_rgba(0,0,0,0.72)] md:col-span-2' : 'border-white/[0.07] hover:border-white/[0.12]' }} {{ $wysiwygAsDesktop ? 'expert-program-card--wysiwyg-as-desktop' : '' }}"
    data-program-slug="{{ e($program->slug) }}"
    @if ($focalStyleFromAlpine)
        @if (filled($focalPreviewMode))
            x-bind:style="typeof programCardArticleStyleForPreview === 'function' ? programCardArticleStyleForPreview(@js($focalPreviewMode)) : (typeof programCardArticleStyle === 'function' ? programCardArticleStyle() : '')"
        @else
            x-bind:style="typeof programCardArticleStyle === 'function' ? programCardArticleStyle() : ''"
        @endif
    @else
        {!! $articleStyleAttr !!}
    @endif
    @if($bindProgramsMore && $pi >= 3)
        x-bind:class="{ 'max-lg:hidden': !programsMore }"
    @endif
>
    <div class="expert-program-card__bg pointer-events-none" aria-hidden="true"></div>

    @if($hasPaneMedia)
        <div class="expert-program-card__media order-1 w-full shrink-0">
            <div class="expert-program-card__media-layer">
                @if($forcedMode === 'auto')
                    <picture>
                        @if($useMobileSource)
                            <source media="(max-width: 1023px)" srcset="{{ e($mobileUrl) }}" />
                        @endif
                        <img
                            src="{{ e($desktopUrl) }}"
                            alt="{{ e($coverAlt) }}"
                            class="expert-program-card__media-img h-full w-full object-cover"
                            width="1200"
                            height="640"
                            loading="{{ $pi < 3 ? 'eager' : 'lazy' }}"
                            decoding="async"
                            onerror="var w=this.closest('.expert-program-card__media');if(w)w.remove()"
                        />
                    </picture>
                @elseif($forcedMode === 'mobile')
                    <img
                        src="{{ e($mobileUrl) }}"
                        alt="{{ e($coverAlt) }}"
                        class="expert-program-card__media-img h-full w-full object-cover"
                        width="1200"
                        height="640"
                        loading="eager"
                        decoding="async"
                        onerror="var w=this.closest('.expert-program-card__media');if(w)w.remove()"
                    />
                @else
                    <img
                        src="{{ e($desktopUrl) }}"
                        alt="{{ e($coverAlt) }}"
                        class="expert-program-card__media-img h-full w-full object-cover"
                        width="1200"
                        height="640"
                        loading="eager"
                        decoding="async"
                        onerror="var w=this.closest('.expert-program-card__media');if(w)w.remove()"
                    />
                @endif
            </div>
            <div class="expert-program-card__media-scrim" aria-hidden="true"></div>
        </div>
    @endif

    <div class="expert-program-card__main relative z-[1] order-2 flex min-w-0 flex-1 flex-col px-5 pb-6 sm:px-8 sm:pb-8 xl:px-10 xl:pb-10">
        <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-start sm:justify-between sm:gap-6 lg:gap-8">
            <div class="min-w-0 flex-1">
                @if($program->is_featured)
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="flex h-1.5 w-1.5 rounded-full bg-moto-amber ring-2 ring-moto-amber/40"></span>
                        <span class="text-[0.65rem] font-bold uppercase tracking-widest text-moto-amber/90">Флагманский модуль</span>
                    </div>
                @endif
                <h3 class="{{ $program->is_featured ? 'text-2xl sm:text-3xl' : 'text-xl sm:text-2xl' }} text-balance font-bold leading-tight text-white/95">{{ $program->title }}</h3>
            </div>
            <div class="expert-program-card__cta-head flex w-full shrink-0 flex-col gap-3 border-t border-white/[0.1] pt-5 sm:w-auto sm:min-w-[10.5rem] sm:border-t-0 sm:pt-0 sm:text-right lg:min-w-[11.5rem]">
                @if($price !== null)
                    <div class="flex flex-col gap-1 sm:items-end">
                        @if(filled($program->price_prefix))
                            <span class="text-[11px] font-bold uppercase tracking-wider text-moto-amber/80">{{ $program->price_prefix }}</span>
                        @endif
                        <span class="text-[1.65rem] font-extrabold tracking-tight text-white/95 sm:text-[1.75rem] sm:text-3xl">{{ $price }}</span>
                    </div>
                @else
                    <span class="text-lg font-bold tracking-tight text-white/90 sm:text-xl">По запросу</span>
                @endif
                @if($isPreview)
                    <span class="tenant-btn-primary inline-flex min-h-[3rem] w-full cursor-default select-none items-center justify-center rounded-xl px-5 text-sm font-bold uppercase tracking-wide opacity-80 sm:min-h-[3.25rem] sm:w-full sm:max-w-[17.5rem] sm:self-end" aria-hidden="true">Записаться</span>
                @elseif ($resolvedCtaMode === \App\Tenant\Expert\TenantEnrollmentCtaConfig::MODE_SCROLL)
                    <a
                        href="#expert-inquiry"
                        class="tenant-btn-primary inline-flex min-h-[3rem] w-full items-center justify-center rounded-xl px-5 text-sm font-bold uppercase tracking-wide transition-transform hover:-translate-y-0.5 sm:min-h-[3.25rem] sm:w-full sm:max-w-[17.5rem] sm:self-end"
                        data-expert-prefill-program="{{ e($program->slug) }}"
                        data-expert-prefill-program-title="{{ e($program->title) }}"
                    >Записаться</a>
                @elseif ($resolvedCtaMode === \App\Tenant\Expert\TenantEnrollmentCtaConfig::MODE_PAGE)
                    <a
                        href="{{ $enrollmentUrl }}?program={{ rawurlencode($program->slug) }}"
                        class="tenant-btn-primary inline-flex min-h-[3rem] w-full items-center justify-center rounded-xl px-5 text-sm font-bold uppercase tracking-wide transition-transform hover:-translate-y-0.5 sm:min-h-[3.25rem] sm:w-full sm:max-w-[17.5rem] sm:self-end"
                    >Записаться</a>
                @else
                    <button
                        type="button"
                        class="tenant-btn-primary inline-flex min-h-[3rem] w-full cursor-pointer items-center justify-center rounded-xl border-0 px-5 text-sm font-bold uppercase tracking-wide transition-transform hover:-translate-y-0.5 sm:min-h-[3.25rem] sm:w-full sm:max-w-[17.5rem] sm:self-end"
                        data-rb-program-enrollment-cta="1"
                        data-expert-prefill-program="{{ e($program->slug) }}"
                        data-expert-prefill-program-title="{{ e($program->title) }}"
                    >Записаться</button>
                @endif
            </div>
        </div>

        <div class="expert-program-card__details mt-8 min-w-0 w-full space-y-8 sm:mt-10 sm:space-y-10">
            @if($audience !== [] || filled($program->teaser))
                <div class="min-w-0 w-full max-w-none">
                    <p class="text-[0.7rem] font-bold uppercase tracking-[0.14em] text-white/55">Кому подходит</p>
                    @if($audience !== [])
                        <ul class="mt-4 space-y-3.5 text-[15px] leading-[1.65] text-silver/90 sm:text-base sm:leading-relaxed">
                            @foreach($audienceHead as $line)
                                <li class="flex gap-3.5">
                                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-moto-amber/65" aria-hidden="true"></span>
                                    <span class="min-w-0">{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($audienceMore !== [])
                            <details class="expert-program-card__more mt-4 rounded-xl border border-white/[0.08] bg-white/[0.02] px-4 py-3 sm:px-5 sm:py-3.5">
                                <summary class="cursor-pointer list-none text-[13px] font-semibold text-moto-amber/90 outline-none transition hover:text-moto-amber focus-visible:rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber [&::-webkit-details-marker]:hidden">
                                    <span class="inline-flex items-center gap-2">
                                        <span aria-hidden="true" class="text-moto-amber">+</span>
                                        Развернуть список (ещё {{ count($audienceMore) }})
                                    </span>
                                </summary>
                                <ul class="mt-4 space-y-3.5 border-t border-white/[0.06] pt-4 text-[15px] leading-[1.65] text-silver/90 sm:text-base sm:leading-relaxed">
                                    @foreach($audienceMore as $line)
                                        <li class="flex gap-3.5">
                                            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-moto-amber/65" aria-hidden="true"></span>
                                            <span class="min-w-0">{{ $line }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    @else
                        <p class="mt-4 text-[15px] leading-relaxed text-silver/90 sm:text-base">{{ $program->teaser }}</p>
                    @endif
                </div>
            @endif

            @if($outcomes !== [] || filled($program->description))
                <div class="min-w-0 w-full max-w-none">
                    <p class="text-[0.7rem] font-bold uppercase tracking-[0.14em] text-moto-amber/75">Результат</p>
                    @if($outcomes !== [])
                        <ul class="mt-4 space-y-3.5 text-[15px] font-medium leading-[1.65] text-white/90 sm:text-base sm:leading-relaxed">
                            @foreach($outcomesHead as $line)
                                <li class="flex gap-3.5">
                                    <svg class="mt-0.5 h-[1.125rem] w-[1.125rem] shrink-0 text-moto-amber" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span class="min-w-0">{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($outcomesMore !== [])
                            <details class="expert-program-card__more mt-4 rounded-xl border border-moto-amber/20 bg-moto-amber/[0.04] px-4 py-3 sm:px-5 sm:py-3.5">
                                <summary class="cursor-pointer list-none text-[13px] font-semibold text-moto-amber outline-none transition hover:text-moto-amber/90 focus-visible:rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber [&::-webkit-details-marker]:hidden">
                                    <span class="inline-flex items-center gap-2">
                                        <span aria-hidden="true">+</span>
                                        Развернуть список (ещё {{ count($outcomesMore) }})
                                    </span>
                                </summary>
                                <ul class="mt-4 space-y-3.5 border-t border-moto-amber/15 pt-4 text-[15px] font-medium leading-[1.65] text-white/90 sm:text-base sm:leading-relaxed">
                                    @foreach($outcomesMore as $line)
                                        <li class="flex gap-3.5">
                                            <svg class="mt-0.5 h-[1.125rem] w-[1.125rem] shrink-0 text-moto-amber" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            <span class="min-w-0">{{ $line }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    @else
                        <p class="mt-4 text-[15px] font-medium leading-relaxed text-white/90 sm:text-base">{{ $program->description }}</p>
                    @endif
                </div>
            @endif
        </div>

        @if($formatLine !== '' || filled($program->duration_label))
            <div class="mt-10 min-w-0 w-full max-w-none border-t border-white/[0.07] pt-8 sm:mt-12">
                @if($formatLine !== '')
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-white/45">Формат / длительность</p>
                    <p class="mt-2 text-[14px] font-medium leading-snug text-white/75 sm:text-[15px]">{{ $formatLine }}</p>
                @elseif(filled($program->duration_label))
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-white/45">Формат</p>
                    <p class="mt-2 text-[14px] font-medium text-white/75 sm:text-[15px]">{{ $program->duration_label }}</p>
                @endif
            </div>
        @endif
    </div>
</article>
