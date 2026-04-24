@php
    use App\Support\Storage\TenantPublicAssetResolver;
    use App\Support\Typography\RussianTypography;
    use App\Tenant\Expert\ExpertBrandMediaUrl;

    $heading = $data['heading'] ?? '';
    $sub = $data['subheading'] ?? '';
    $headingTied = filled($heading) ? RussianTypography::tiePrepositionsToNextWord($heading) : '';
    $subTied = filled($sub) ? RussianTypography::tiePrepositionsToNextWord($sub) : '';
    $btn = $data['button_text'] ?? '';
    $url = $data['button_url'] ?? '#';
    $btn2 = trim((string) ($data['secondary_button_text'] ?? ''));
    $url2 = trim((string) ($data['secondary_button_url'] ?? ''));
    $bgRaw = is_string($data['background_image'] ?? null) ? trim((string) $data['background_image']) : '';
    $bgUrl = $bgRaw !== ''
        ? TenantPublicAssetResolver::resolveForCurrentTenant($bgRaw)
        : theme_platform_asset_url('marketing/hero-bg.png');
    $hasCustomBg = is_string($data['background_image'] ?? null) && trim((string) $data['background_image']) !== '';
    $vSrc = trim((string) ($data['video_src'] ?? ''));
    $vPoster = trim((string) ($data['video_poster'] ?? ''));
    $vSrcUrl = $vSrc !== '' ? ExpertBrandMediaUrl::resolve($vSrc) : '';
    $vPosterUrl = $vPoster !== '' ? ExpertBrandMediaUrl::resolve($vPoster) : '';
    $isWorksHero = ($section->section_key ?? '') === 'works_hero';
    /** /raboty: без постера <video> остаётся чёрным до play — дублируем фон секции или дефолтный platform hero. */
    $vPosterDisplayUrl = $vPosterUrl;
    if ($vPosterDisplayUrl === '' && $isWorksHero && filled($vSrcUrl) && filled($bgUrl)) {
        $vPosterDisplayUrl = $bgUrl;
    }
    $videoDeferred = ! empty($data['video_deferred']) && ! $isWorksHero;
    $bgFetchHigh = $hasCustomBg && ! ($videoDeferred && $vSrcUrl !== '' && (filled($vPosterUrl) || ($isWorksHero && filled($bgUrl))));
    /** Один h1 на странице: h1 только если shell в page.blade не рендерит h1 (первый блок — hero/works_hero). */
    $bdHeroH1 = ($isFirstVisibleExtra ?? false)
        && in_array((string) ($section->section_key ?? ''), ['hero', 'works_hero'], true);
    $worksHeroVideoLayout = $isWorksHero && ! $videoDeferred && filled($vSrcUrl);
@endphp
<section @class([
    'relative overflow-hidden rounded-2xl border border-white/10 bg-carbon/90',
    'min-h-[min(52vh,28rem)] sm:min-h-[min(44vh,26rem)]' => ! $worksHeroVideoLayout,
    'min-h-0 sm:min-h-[min(48vh,30rem)] lg:min-h-[min(52vh,32rem)]' => $worksHeroVideoLayout,
])>
    @if (filled($bgUrl))
        <div class="pointer-events-none absolute inset-0">
            @if ($hasCustomBg)
                <img
                    src="{{ e($bgUrl) }}"
                    alt=""
                    class="h-full w-full object-cover"
                    @if ($bgFetchHigh) fetchpriority="high" @endif
                    decoding="async"
                />
            @else
                <div class="h-full w-full bg-cover bg-center opacity-40" style="background-image: url('{{ e($bgUrl) }}');" role="img" aria-hidden="true"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/60 to-black/25" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" aria-hidden="true"></div>
        </div>
    @endif
    <div @class([
        'relative z-10 flex flex-col p-6 sm:p-10',
        'gap-8 lg:grid lg:grid-cols-2 lg:items-center lg:gap-10 xl:gap-12' => $worksHeroVideoLayout,
    ])>
        <div @class([
            'flex flex-col' => true,
            'min-w-0 max-w-2xl' => ! $worksHeroVideoLayout,
            'min-w-0 lg:max-w-none' => $worksHeroVideoLayout,
        ])>
            @if (filled($heading))
                @if ($bdHeroH1)
                    <h1 class="text-balance text-2xl font-bold text-white sm:text-3xl lg:text-4xl">{{ $headingTied }}</h1>
                @else
                    <h2 class="text-balance text-2xl font-bold text-white sm:text-3xl lg:text-4xl">{{ $headingTied }}</h2>
                @endif
            @endif
            @if (filled($sub))
                <p class="mt-3 text-pretty text-base leading-relaxed text-zinc-300 sm:text-lg">{{ $subTied }}</p>
            @endif
            @if (filled($btn) || filled($btn2))
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @if (filled($btn))
                        <a href="{{ e($url) }}" class="inline-flex min-h-11 items-center rounded-xl bg-[#36C7FF] px-5 py-2.5 text-sm font-semibold text-carbon transition hover:bg-[#5ad2ff]">{{ $btn }}</a>
                    @endif
                    @if (filled($btn2) && filled($url2))
                        <a href="{{ e($url2) }}" class="inline-flex min-h-11 items-center rounded-xl border border-white/25 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">{{ $btn2 }}</a>
                    @endif
                </div>
            @endif
            @if (! $worksHeroVideoLayout && ! $videoDeferred && filled($vSrcUrl))
                <div class="mt-8 w-full max-w-3xl sm:mt-10">
                    <video
                        class="w-full overflow-hidden rounded-xl border border-white/10"
                        controls
                        playsinline
                        preload="metadata"
                        @if (filled($vPosterDisplayUrl)) poster="{{ e($vPosterDisplayUrl) }}" @endif
                    >
                        <source src="{{ e($vSrcUrl) }}" type="{{ str_ends_with(strtolower($vSrc), '.webm') ? 'video/webm' : 'video/mp4' }}" />
                    </video>
                </div>
            @endif
        </div>
        @if ($worksHeroVideoLayout)
            <div
                class="bd-works-hero-video w-full min-w-0 lg:max-w-none lg:justify-self-end xl:max-w-xl 2xl:max-w-2xl"
                x-data="{
                    started: false,
                    start() {
                        const v = this.$refs.bdWorksHeroVideo
                        if (v) {
                            v.play().catch(() => {})
                        }
                        this.started = true
                    },
                }"
            >
                <div class="relative aspect-video overflow-hidden rounded-2xl bg-black shadow-[0_24px_60px_-20px_rgba(0,0,0,0.85),0_0_42px_-12px_rgba(54,199,255,0.22)] ring-1 ring-white/15">
                    <video
                        x-ref="bdWorksHeroVideo"
                        class="h-full w-full object-cover"
                        playsinline
                        preload="metadata"
                        :controls="started"
                        aria-label="Видео с примерами работ"
                        @play="started = true"
                        @if (filled($vPosterDisplayUrl)) poster="{{ e($vPosterDisplayUrl) }}" @endif
                    >
                        <source src="{{ e($vSrcUrl) }}" type="{{ str_ends_with(strtolower($vSrc), '.webm') ? 'video/webm' : 'video/mp4' }}" />
                    </video>
                    <button
                        type="button"
                        class="group absolute inset-0 z-10 flex cursor-pointer items-center justify-center rounded-2xl border-0 bg-gradient-to-t from-black/55 via-black/15 to-black/25 text-left transition hover:from-black/45 hover:via-black/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#36C7FF] focus-visible:ring-offset-2 focus-visible:ring-offset-carbon"
                        x-cloak
                        x-show="!started"
                        @click.prevent="start()"
                        aria-label="Воспроизвести видео"
                    >
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-[#36C7FF] text-carbon shadow-lg shadow-black/40 ring-4 ring-black/35 transition duration-200 ease-out group-hover:scale-105 sm:h-[4.5rem] sm:w-[4.5rem]">
                            <svg class="ms-1 h-7 w-7 sm:h-8 sm:w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </span>
                    </button>
                </div>
                <p class="mt-2 text-center text-xs text-zinc-500 sm:text-left">Ролик со звуком — после запуска доступны пауза и полноэкранный режим.</p>
            </div>
        @endif
        @if ($videoDeferred && filled($vSrcUrl))
            {{-- Услуги: видео ниже текстового блока; poster по возможности, без autoplay. --}}
            <div class="relative z-10 mt-10 w-full max-w-4xl border-t border-white/10 pt-8">
                <video
                    class="w-full overflow-hidden rounded-xl border border-white/10"
                    controls
                    playsinline
                    preload="none"
                    @if (filled($vPosterDisplayUrl)) poster="{{ e($vPosterDisplayUrl) }}" @endif
                >
                    <source src="{{ e($vSrcUrl) }}" type="{{ str_ends_with(strtolower($vSrc), '.webm') ? 'video/webm' : 'video/mp4' }}" />
                </video>
            </div>
        @endif
    </div>
</section>
