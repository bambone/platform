@php
    use App\MediaPresentation\ExpertHeroBackgroundPresentationResolver;
    use App\Support\Typography\RussianTypography;
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $heading = trim((string) ($data['heading'] ?? ''));
    if ($heading === '') {
        return;
    }
    $sub = trim((string) ($data['subheading'] ?? ''));
    $p1 = trim((string) ($data['primary_cta_label'] ?? ''));
    $a1 = trim((string) ($data['primary_cta_anchor'] ?? ''));
    $p2 = trim((string) ($data['secondary_cta_label'] ?? ''));
    $a2 = trim((string) ($data['secondary_cta_anchor'] ?? ''));
    $badges = is_array($data['trust_badges'] ?? null) ? $data['trust_badges'] : [];
    $badges = array_slice($badges, 0, 3);
    $overlay = (bool) ($data['overlay_dark'] ?? true);
    $heroUrl = trim((string) ($data['hero_image_url'] ?? ''));
    if ($heroUrl === '' && isset($data['hero_image_slot']) && is_array($data['hero_image_slot'])) {
        $heroUrl = trim((string) ($data['hero_image_slot']['url'] ?? ''));
    }
    $heroUrl = ExpertBrandMediaUrl::resolve($heroUrl);
    $heroAlt = trim((string) ($data['hero_image_alt'] ?? ''));
    if ($heroAlt === '') {
        $heroAlt = function_exists('tenant') && tenant()?->theme_key === 'advocate_editorial'
            ? 'Адвокат — фото для сайта'
            : 'Марат Афлятунов — инструктор по вождению';
    }
    $eyebrow = trim((string) ($data['hero_eyebrow'] ?? ''));
    if ($eyebrow === '') {
        $eyebrow = function_exists('tenant') && tenant()?->theme_key === 'advocate_editorial'
            ? 'Адвокат • Челябинск и область'
            : 'Инструктор • Челябинск и область';
    }
    $hasPhoto = $heroUrl !== '';
    $videoUrl = ExpertBrandMediaUrl::resolve(trim((string) ($data['hero_video_url'] ?? '')));
    $videoPoster = ExpertBrandMediaUrl::resolve(trim((string) ($data['hero_video_poster_url'] ?? '')));
    $videoTrigger = trim((string) ($data['video_trigger_label'] ?? ''));
    if ($videoTrigger === '') {
        $videoTrigger = 'Смотреть видео';
    }
    $hasVideo = $videoUrl !== '';
    $dialogId = 'expert-hero-video-'.(int) data_get($section ?? [], 'id', 0);
    $headingDisplay = RussianTypography::tiePrepositionsToNextWord($heading);
    $subDisplay = $sub !== '' ? RussianTypography::tiePrepositionsToNextWord($sub) : '';
    $heroPresentationStyle = $hasPhoto
        ? app(ExpertHeroBackgroundPresentationResolver::class)->sectionStyleAttribute(is_array($data) ? $data : [])
        : '';
    $heroSectionStyle = '';
    if ($hasPhoto) {
        $heroSectionStyle = $heroPresentationStyle;
        if ($overlay) {
            $heroSectionStyle .= '; --ex-hero-vignette: rgba(4,8,18,0.25)';
        }
    }
    $heroSectionStyleAttr = $heroSectionStyle !== '' ? ' style="'.e($heroSectionStyle).'"' : '';
@endphp
@push('tenant-preload')
    @if($hasPhoto && $heroUrl !== '')
        <link rel="preload" as="image" href="{{ e($heroUrl) }}" fetchpriority="high">
    @endif
@endpush
{{-- Full-bleed hero: desktop — текст слева + crop справа; mobile — герой сверху чистый, типографика и CTA внизу на нижнем градиенте (без карточки-подложки). --}}
<section
    class="expert-hero-cinematic relative z-0 mb-10 sm:mb-20 lg:mb-28 @if($hasPhoto) expert-hero-cinematic--photo @endif"
    data-expert-hero="1"
    {!! $heroSectionStyleAttr !!}
>
    <div class="expert-hero-cinematic__bleed expert-hero-cinematic__bleed--stage">
        @if($hasPhoto)
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__stage-ambient" aria-hidden="true"></div>
            <div class="expert-hero-cinematic__photo-layer">
                <img
                    src="{{ e($heroUrl) }}"
                    alt="{{ e($heroAlt) }}"
                    class="expert-hero-cinematic__photo"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                >
            </div>
        @endif

        @if(! $hasPhoto)
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__bg" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__glow" aria-hidden="true"></div>
        @endif

        {{-- Многослойные оверлеи поверх фото (читаемость + «кинематограф») --}}
        @if($hasPhoto)
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__overlay-base" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__overlay-left" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__overlay-bottom" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__overlay-right-soft" aria-hidden="true"></div>
        @endif

        <div @class([
            'expert-hero-cinematic__stage-inner relative z-10 mx-auto flex w-full max-w-[100rem] flex-col px-4 pb-[max(1.25rem,env(safe-area-inset-bottom,0px))] pt-1 sm:px-8 sm:pb-10 sm:pt-6 lg:min-h-[min(88vh,52rem)] lg:flex-row lg:items-center lg:justify-start lg:px-12 lg:pb-16 lg:pt-10 xl:px-14 xl:pt-14',
            'min-h-0 flex-1 justify-end' => $hasPhoto,
            'lg:flex-none' => $hasPhoto,
        ])>
            @if($hasPhoto)
                {{-- Mobile: забирает свободную высоту — типографика и CTA прижимаются к низу первого viewport (poster). --}}
                <div class="expert-hero-cinematic__poster-spacer min-h-0 w-full flex-1 basis-0 lg:hidden" aria-hidden="true"></div>
            @endif
            <div class="expert-hero-cinematic__copy relative z-20 flex min-w-0 w-full max-w-[min(100%,32rem)] shrink-0 flex-col justify-center sm:max-w-2xl md:max-w-3xl lg:max-w-[min(48rem,56vw)] lg:flex-1 lg:pr-6 xl:max-w-[min(52rem,54vw)] xl:pr-8">
                <p class="expert-hero-cinematic__eyebrow mb-2 text-[0.6rem] font-bold uppercase tracking-[0.2em] text-moto-amber/90 sm:mb-4 sm:text-xs">{{ $eyebrow }}</p>
                <h1 class="expert-hero-cinematic__headline text-pretty text-[clamp(1.4rem,4.2vw,2.5rem)] font-extrabold leading-[1.14] tracking-tight text-white sm:text-5xl sm:leading-[1.12] lg:text-[clamp(2.5rem,3.6vw,3.35rem)] lg:leading-[1.1] xl:text-[clamp(2.75rem,3.4vw,3.75rem)] xl:leading-[1.08]">{{ $headingDisplay }}</h1>
                @if($sub !== '')
                    {{-- Mobile: максимум ~2 строки смысла; полный текст с md. --}}
                    <p class="expert-hero-cinematic__lead mt-3 max-w-none text-pretty text-[15px] font-normal leading-snug text-white/92 line-clamp-2 md:mt-5 md:line-clamp-none md:text-lg md:leading-[1.65] lg:mt-4 lg:text-[19px]">{{ $subDisplay }}</p>
                @endif

                <div class="mt-4 flex w-full max-w-2xl flex-col gap-2.5 sm:mt-7 sm:max-w-none sm:flex-row sm:flex-wrap sm:items-center sm:gap-4 md:mt-8">
                    @if($p1 !== '' && $a1 !== '')
                        <a href="{{ $a1 }}" class="inline-flex min-h-[2.85rem] w-full shrink-0 items-center justify-center rounded-xl bg-moto-amber px-5 text-[14px] font-bold text-black shadow-lg shadow-moto-amber/18 transition-transform hover:scale-[1.02] sm:min-h-[3.15rem] sm:w-auto sm:rounded-2xl sm:px-8 sm:text-[15px]">{{ $p1 }}</a>
                    @endif
                    @if($p2 !== '' && $a2 !== '')
                        <a href="{{ $a2 }}" class="hidden min-h-[3rem] items-center justify-center rounded-xl border border-white/15 px-5 text-[14px] font-semibold text-white/90 transition hover:border-moto-amber/35 hover:text-white md:inline-flex md:w-auto md:rounded-2xl md:px-6 md:text-sm">{{ $p2 }}</a>
                    @endif
                    @if($hasVideo)
                        <button
                            type="button"
                            class="group hidden h-[3.5rem] items-center gap-3 rounded-2xl px-2 pr-6 text-white/85 transition hover:text-white sm:inline-flex lg:hidden"
                            data-expert-video-open="{{ e($dialogId) }}"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-white transition group-hover:bg-white/20 group-hover:text-moto-amber" aria-hidden="true">
                                <svg class="h-5 w-5 translate-x-[1px]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                            </span>
                            <span class="text-sm font-semibold tracking-wide">{{ $videoTrigger }}</span>
                        </button>
                    @endif
                </div>

                @if($hasVideo && $hasPhoto)
                    <div class="mt-5 hidden w-full max-w-2xl lg:block">
                        <button
                            type="button"
                            class="expert-hero-cinematic__video-cta-lg group inline-flex w-full max-w-xl items-center gap-3 rounded-xl border border-white/12 bg-[#0a0f18]/45 px-3 py-2.5 text-left backdrop-blur-md transition hover:border-moto-amber/30 hover:bg-[#0e1424]/75 sm:max-w-lg"
                            data-expert-video-open="{{ e($dialogId) }}"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-moto-amber text-black shadow-md shadow-moto-amber/25 ring-1 ring-moto-amber/35" aria-hidden="true">
                                <svg class="h-5 w-5 translate-x-[1px]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[10px] font-bold uppercase tracking-widest text-moto-amber/90">Видео</span>
                                <span class="mt-0.5 block text-sm font-semibold leading-snug tracking-wide text-white/95">{{ $videoTrigger }}</span>
                            </span>
                        </button>
                    </div>
                @endif

                @if($hasVideo)
                    {{-- Только узкая ширина: на sm+ видео — в ряду с CTA (без второго блока). --}}
                    <div class="mt-3 sm:hidden">
                        <button
                            type="button"
                            class="expert-hero-cinematic__video-cta group inline-flex w-full max-w-2xl items-center gap-2.5 rounded-lg border border-white/22 bg-transparent py-2 pl-2.5 pr-3 text-left shadow-[0_8px_24px_rgba(0,0,0,0.35)] transition hover:border-moto-amber/40"
                            data-expert-video-open="{{ e($dialogId) }}"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-moto-amber text-black shadow shadow-moto-amber/20 ring-1 ring-moto-amber/35" aria-hidden="true">
                                <svg class="h-4 w-4 translate-x-[1px]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[9px] font-bold uppercase tracking-widest text-moto-amber/90">Видео</span>
                                <span class="mt-0.5 block text-[13px] font-semibold leading-tight tracking-wide text-white/95">{{ $videoTrigger }}</span>
                            </span>
                        </button>
                    </div>
                @endif

                @if(count($badges) > 0)
                    <ul class="expert-hero-cinematic__trust-mobile mt-3 flex flex-col gap-1 lg:hidden" aria-label="Коротко о формате">
                        @foreach($badges as $b)
                            @php $t = trim((string) ($b['text'] ?? '')); @endphp
                            @if($t !== '')
                                <li class="inline-flex items-start gap-1.5 text-[11px] font-medium leading-snug text-white/78">
                                    <svg class="mt-0.5 h-3 w-3 shrink-0 text-moto-amber/75" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $t }}
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    <ul class="mt-8 hidden flex-col gap-2.5 sm:mt-10 lg:flex">
                        @foreach($badges as $b)
                            @php $t = trim((string) ($b['text'] ?? '')); @endphp
                            @if($t !== '')
                                <li class="inline-flex items-start gap-2.5 text-[13px] font-medium leading-snug tracking-wide text-white/82">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-moto-amber/85" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $t }}
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        @if(! $hasPhoto)
            <div class="relative z-10 mx-auto w-full max-w-[100rem] px-4 pb-10 pt-0 sm:px-8 lg:px-12">
                <div class="expert-hero-bg flex min-h-[16rem] items-center justify-center rounded-2xl border border-white/10 sm:min-h-[20rem] lg:min-h-[28rem]">
                    <p class="text-sm font-medium text-white/60">Добавьте фото в блоке Hero в конструкторе страницы.</p>
                </div>
            </div>
        @endif
    </div>
</section>

@if($hasVideo)
    <dialog id="{{ e($dialogId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-labelledby="{{ e($dialogId) }}-title">
        <div class="expert-video-dialog__panel">
            <div class="expert-video-dialog__head">
                <p id="{{ e($dialogId) }}-title" class="text-sm font-semibold text-white">{{ $videoTrigger }}</p>
                <form method="dialog">
                    <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                </form>
            </div>
            <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                <video class="expert-video-dialog__video" controls playsinline preload="none" @if($videoPoster !== '') poster="{{ e($videoPoster) }}" @endif data-expert-dialog-src="{{ e($videoUrl) }}"></video>
            </div>
        </div>
    </dialog>
@endif

@include('tenant.partials.expert-video-dialog-script')
