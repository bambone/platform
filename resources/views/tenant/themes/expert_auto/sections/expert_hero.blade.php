@php
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
    $badges = array_slice($badges, 0, 4);
    $overlay = (bool) ($data['overlay_dark'] ?? true);
    $heroUrl = trim((string) ($data['hero_image_url'] ?? ''));
    if ($heroUrl === '' && isset($data['hero_image_slot']) && is_array($data['hero_image_slot'])) {
        $heroUrl = trim((string) ($data['hero_image_slot']['url'] ?? ''));
    }
    $heroUrl = ExpertBrandMediaUrl::resolve($heroUrl);
    $heroAlt = trim((string) ($data['hero_image_alt'] ?? ''));
    if ($heroAlt === '') {
        $heroAlt = 'Марат Афлятунов — инструктор по вождению';
    }
    $hasPhoto = $heroUrl !== '';
    $videoUrl = ExpertBrandMediaUrl::resolve(trim((string) ($data['hero_video_url'] ?? '')));
    $videoPoster = ExpertBrandMediaUrl::resolve(trim((string) ($data['hero_video_poster_url'] ?? '')));
    $videoTrigger = trim((string) ($data['video_trigger_label'] ?? ''));
    if ($videoTrigger === '') {
        $videoTrigger = 'Смотреть, как проходят занятия';
    }
    $hasVideo = $videoUrl !== '';
    $dialogId = 'expert-hero-video-'.(int) ($section->id ?? 0);
@endphp
{{-- Сценический hero: без лишнего текста и «коробки», крупный визуал, deep navy + золото --}}
<section
    class="expert-hero-cinematic relative z-0 mb-12 sm:mb-20 lg:mb-28"
    data-expert-hero="1"
    @if($hasPhoto && $overlay) style="--ex-hero-vignette: rgba(4,8,18,0.25);" @endif
>
    <div class="expert-hero-cinematic__bleed">
        <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__bg" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__glow" aria-hidden="true"></div>

        @if($hasPhoto)
            <div class="pointer-events-none absolute inset-0 lg:hidden" aria-hidden="true">
                <img src="{{ e($heroUrl) }}" alt="" class="h-[42vh] min-h-[14rem] w-full object-cover object-[center_24%] opacity-50" loading="eager" fetchpriority="high" decoding="async" width="1200" height="800">
                <div class="absolute inset-0 bg-gradient-to-b from-[#0a1020]/95 via-[#0c1224]/88 to-[#0e1428]"></div>
            </div>
        @endif

        <div class="relative z-10 mx-auto grid max-w-[min(96rem,calc(100vw-1rem))] items-center gap-8 px-4 pb-12 pt-10 sm:gap-10 sm:px-6 sm:pb-16 sm:pt-14 lg:min-h-[min(90vh,54rem)] lg:grid-cols-12 lg:gap-8 lg:px-10 lg:pb-20 lg:pt-16 xl:gap-12">
            <div class="flex flex-col justify-center lg:col-span-4 lg:max-w-none lg:pr-2 xl:col-span-5 xl:pr-6">
                <p class="expert-hero-cinematic__eyebrow mb-4 text-[0.65rem] font-semibold uppercase tracking-[0.28em] text-moto-amber sm:mb-5 sm:text-xs">Инструктор • Челябинск и область</p>
                <h1 class="expert-hero-cinematic__h1 text-balance font-extrabold leading-[1.05] tracking-tight text-white">{{ $heading }}</h1>
                @if($sub !== '')
                    <p class="expert-hero-cinematic__lead mt-5 text-pretty font-normal leading-relaxed text-white/86 sm:mt-6">{{ $sub }}</p>
                @endif

                <div class="expert-hero-cinematic__cta-row mt-8 flex flex-col gap-3 sm:mt-9 sm:flex-row sm:flex-wrap sm:items-center">
                    @if($p1 !== '' && $a1 !== '')
                        <a href="{{ $a1 }}" class="expert-hero-cinematic__btn-primary tenant-btn-primary inline-flex min-h-[3.35rem] justify-center px-9 text-base font-bold shadow-xl shadow-black/35">{{ $p1 }}</a>
                    @endif
                    @if($p2 !== '' && $a2 !== '')
                        <a href="{{ $a2 }}" class="expert-hero-cinematic__btn-secondary inline-flex min-h-[3.35rem] items-center justify-center rounded-xl border border-white/18 bg-white/[0.04] px-8 text-base font-semibold text-white backdrop-blur-md transition hover:border-moto-amber/35 hover:bg-moto-amber/[0.08]">{{ $p2 }}</a>
                    @endif
                </div>

                @if($hasVideo)
                    <div class="mt-7 sm:mt-8 lg:hidden">
                        <button
                            type="button"
                            class="expert-hero-cinematic__video-cta group inline-flex w-full max-w-md items-center gap-4 rounded-2xl border border-white/14 bg-white/[0.05] px-4 py-4 text-left backdrop-blur-md transition hover:border-moto-amber/40 hover:bg-moto-amber/[0.07]"
                            data-expert-video-open="{{ e($dialogId) }}"
                        >
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-moto-amber text-lg text-[#10131a] shadow-lg shadow-moto-amber/25 ring-2 ring-moto-amber/40" aria-hidden="true">
                                <svg class="h-6 w-6 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M8 5v14l11-7L8 5z"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-bold uppercase tracking-widest text-moto-amber">Видео</span>
                                <span class="mt-0.5 block text-base font-semibold text-white">{{ $videoTrigger }}</span>
                            </span>
                        </button>
                    </div>
                @endif

                @if(count($badges) > 0)
                    <ul class="expert-hero-cinematic__badges mt-10 flex flex-wrap gap-x-4 gap-y-2 sm:mt-12">
                        @foreach($badges as $b)
                            @php $t = trim((string) ($b['text'] ?? '')); @endphp
                            @if($t !== '')
                                <li class="expert-trust-badge expert-trust-badge--hero-lite">{{ $t }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="relative min-h-[18rem] sm:min-h-[22rem] lg:col-span-8 lg:min-h-0 xl:col-span-7">
                @if($hasPhoto)
                    <div class="expert-hero-cinematic__stage relative mx-auto w-full max-w-2xl lg:mx-0 lg:max-w-none lg:translate-x-[1%] lg:pl-2 xl:translate-x-[3%] xl:pl-4">
                        <div class="expert-hero-cinematic__photo-shell relative overflow-hidden rounded-2xl shadow-[0_48px_100px_-28px_rgba(0,0,0,0.75)] ring-1 ring-white/[0.07] lg:rounded-[1.75rem] lg:min-h-[min(82vh,46rem)]">
                            <img
                                src="{{ e($heroUrl) }}"
                                alt="{{ e($heroAlt) }}"
                                class="expert-hero-cinematic__img h-full min-h-[20rem] w-full object-cover object-[center_22%] sm:min-h-[24rem] lg:min-h-[min(78vh,44rem)]"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                                width="1100"
                                height="1400"
                            >
                            @if($overlay)
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#0a1028]/95 via-[#0a1028]/15 to-transparent lg:from-[#0a1028]/75 lg:via-transparent lg:to-[#0a1028]/20"></div>
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#0a1028]/50 via-transparent to-transparent lg:from-[#0c1428]/90"></div>
                            @endif
                            @if($hasVideo)
                                <button
                                    type="button"
                                    class="expert-hero-cinematic__play-on-photo group/play absolute bottom-5 right-5 z-20 hidden max-w-[min(100%,17rem)] items-center gap-3 rounded-2xl border border-white/22 bg-[#080c16]/82 px-4 py-3 text-left text-white shadow-[0_20px_50px_-12px_rgba(0,0,0,0.65)] backdrop-blur-lg transition hover:border-moto-amber/55 hover:bg-[#0e1524]/92 hover:shadow-[0_24px_60px_-10px_rgba(201,168,124,0.18)] lg:bottom-7 lg:right-7 lg:flex lg:px-5 lg:py-3.5"
                                    data-expert-video-open="{{ e($dialogId) }}"
                                >
                                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-moto-amber text-[#10131a] shadow-lg ring-2 ring-moto-amber/30 transition group-hover/play:scale-[1.04]" aria-hidden="true">
                                        <svg class="h-7 w-7 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-[0.65rem] font-bold uppercase tracking-widest text-moto-amber/95">На практике</span>
                                        <span class="mt-0.5 block text-sm font-semibold leading-snug sm:text-base">{{ $videoTrigger }}</span>
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="expert-hero-bg flex min-h-[22rem] items-end rounded-3xl border border-white/10 p-8 lg:min-h-[36rem]">
                        <p class="text-sm text-silver">Добавьте фото в блоке Hero в конструкторе страницы.</p>
                    </div>
                @endif
            </div>
        </div>
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
                <video class="expert-video-dialog__video" controls playsinline preload="auto" @if($videoPoster !== '') poster="{{ e($videoPoster) }}" @endif src="{{ e($videoUrl) }}"></video>
            </div>
        </div>
    </dialog>
@endif

@once('expert-video-dialog-script')
    <script>
        (function () {
            function tryPlay(dlg) {
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var v = dlg.querySelector('video');
                        if (!v) return;
                        try {
                            v.muted = false;
                            var p = v.play();
                            if (p && typeof p.catch === 'function') {
                                p.catch(function () {
                                    try {
                                        v.muted = true;
                                        v.play().catch(function () {});
                                    } catch (e2) {}
                                });
                            }
                        } catch (e) {}
                    });
                });
            }
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-expert-video-open]');
                if (!btn) return;
                var id = btn.getAttribute('data-expert-video-open');
                if (!id) return;
                var dlg = document.getElementById(id);
                if (dlg && typeof dlg.showModal === 'function') {
                    dlg.showModal();
                    tryPlay(dlg);
                }
            });
            document.addEventListener('click', function (ev) {
                var t = ev.target;
                if (t && t.tagName === 'DIALOG' && t.classList.contains('expert-video-dialog')) {
                    t.close();
                }
            });
            document.addEventListener('close', function (e) {
                var dlg = e.target;
                if (!dlg || dlg.tagName !== 'DIALOG' || !dlg.classList.contains('expert-video-dialog')) return;
                var v = dlg.querySelector('video');
                if (v) { try { v.pause(); v.currentTime = 0; } catch (err) {} }
            }, true);
        })();
    </script>
@endonce
