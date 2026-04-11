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
    $badges = array_slice($badges, 0, 3);
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
    $dialogId = 'expert-hero-video-'.(int) data_get($section ?? [], 'id', 0);
@endphp
{{-- Сценический hero: без лишнего текста и «коробки», крупный визуал, deep navy + золото --}}
<section
    class="expert-hero-cinematic relative z-0 mb-10 sm:mb-20 lg:mb-28"
    data-expert-hero="1"
    @if($hasPhoto && $overlay) style="--ex-hero-vignette: rgba(4,8,18,0.25);" @endif
>
    <div class="expert-hero-cinematic__bleed">
        <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__bg" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 expert-hero-cinematic__glow" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto grid w-full max-w-[100rem] grid-cols-1 items-start gap-y-3 px-4 pb-6 pt-4 sm:gap-y-7 sm:px-8 sm:pb-12 sm:pt-7 lg:grid-cols-12 lg:items-center lg:gap-x-8 lg:gap-y-0 lg:px-12 lg:pb-16 lg:pt-8 lg:py-20 xl:gap-x-10 xl:py-24">
            {{-- Mobile: eyebrow → H1 → lead → CTA → видео → фото → trust. Desktop: прежняя двухколоночная сетка. --}}
            <div class="relative z-20 flex min-w-0 max-w-[22rem] flex-col justify-center sm:max-w-xl lg:col-span-6 lg:col-start-1 lg:max-w-none lg:pr-0 xl:col-span-5 xl:pr-2">
                <p class="mb-2 text-[0.6rem] font-bold uppercase tracking-[0.2em] text-moto-amber/90 sm:mb-4 sm:text-xs">Инструктор • Челябинск и область</p>
                <h1 class="text-balance text-[clamp(1.35rem,5vw,2.35rem)] font-extrabold leading-[1.12] tracking-tight text-white sm:text-5xl sm:leading-[1.1] lg:text-[3.25rem] lg:leading-[1.1] xl:text-[3.75rem] xl:leading-[1.08]">{{ $heading }}</h1>
                @if($sub !== '')
                    <p class="mt-3 max-w-[34rem] text-pretty text-[14px] font-normal leading-snug text-white/78 line-clamp-2 sm:mt-5 sm:text-lg sm:leading-[1.6] sm:line-clamp-none lg:mt-4 lg:text-[19px]">{{ $sub }}</p>
                @endif

                <div class="mt-4 flex w-full max-w-md flex-col gap-2.5 sm:mt-8 sm:max-w-none sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                    @if($p1 !== '' && $a1 !== '')
                        <a href="{{ $a1 }}" class="inline-flex min-h-[2.85rem] w-full shrink-0 items-center justify-center rounded-xl bg-moto-amber px-5 text-[14px] font-bold text-black shadow-lg shadow-moto-amber/18 transition-transform hover:scale-[1.02] sm:min-h-[3.15rem] sm:w-auto sm:rounded-2xl sm:px-8 sm:text-[15px]">{{ $p1 }}</a>
                    @endif
                    @if($p2 !== '' && $a2 !== '')
                        <a href="{{ $a2 }}" class="hidden min-h-[3rem] items-center justify-center rounded-2xl border border-white/15 px-6 text-sm font-semibold text-white/85 transition hover:border-moto-amber/35 hover:text-white sm:inline-flex sm:w-auto">{{ $p2 }}</a>
                    @endif
                    @if($hasVideo)
                        <button
                            type="button"
                            class="group hidden h-[3.5rem] items-center gap-3 rounded-2xl px-2 pr-6 text-white/80 transition hover:text-white sm:inline-flex lg:hidden"
                            data-expert-video-open="{{ e($dialogId) }}"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-white transition group-hover:bg-white/20 group-hover:text-moto-amber" aria-hidden="true">
                                <svg class="h-5 w-5 translate-x-[1px]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                            </span>
                            <span class="text-sm font-semibold tracking-wide">{{ $videoTrigger }}</span>
                        </button>
                    @endif
                </div>

                @if($hasVideo)
                    <div class="mt-3 sm:mt-6 lg:hidden">
                        <button
                            type="button"
                            class="expert-hero-cinematic__video-cta group inline-flex w-full max-w-md items-center gap-2.5 rounded-lg border border-white/10 bg-[#0a0f18]/50 py-2 pl-2.5 pr-3 text-left backdrop-blur-md transition hover:border-moto-amber/30 hover:bg-[#0e1424]/80"
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
                    <ul class="mt-8 hidden flex-col gap-2.5 sm:mt-10 lg:flex">
                        @foreach($badges as $b)
                            @php $t = trim((string) ($b['text'] ?? '')); @endphp
                            @if($t !== '')
                                <li class="inline-flex items-start gap-2.5 text-[13px] font-medium leading-snug tracking-wide text-white/78">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-moto-amber/85" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $t }}
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="relative z-10 min-w-0 w-full lg:col-span-6 lg:col-start-7 lg:h-full xl:col-span-7 xl:col-start-6">
                @if($hasPhoto)
                    <div class="relative flex w-full flex-col justify-center lg:h-full">
                        <div class="relative overflow-hidden rounded-2xl border border-white/[0.06] shadow-[0_24px_64px_-28px_rgba(0,0,0,0.75)] lg:rounded-[2.5rem] lg:shadow-none">
                            <img
                                src="{{ e($heroUrl) }}"
                                alt="{{ e($heroAlt) }}"
                                class="block aspect-[5/3] w-full max-h-[13rem] object-cover object-[center_28%] sm:aspect-auto sm:max-h-none sm:min-h-[22rem] lg:min-h-[min(85vh,48rem)]"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                            >
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#050609]/75 via-transparent to-transparent opacity-90 sm:opacity-100 lg:hidden" aria-hidden="true"></div>
                            
                            {{-- Градиент растворения для Desktop, чтобы уйти от бокс-модели --}}
                            <div class="pointer-events-none absolute inset-0 hidden bg-gradient-to-r from-[var(--tw-gradient-from,#050609)] from-0% via-[var(--tw-gradient-from,#050609)]/75 via-35% to-transparent to-100% lg:block" style="--tw-gradient-from: #080b13;"></div>

                            @if($hasVideo)
                                <button
                                    type="button"
                                    class="group/play absolute bottom-4 left-4 z-20 hidden max-w-sm items-center gap-4 rounded-full border border-white/10 bg-[#080b13]/80 p-2 pr-6 text-left text-white shadow-2xl backdrop-blur-md transition-colors hover:border-moto-amber/40 hover:bg-[#0c101c] sm:flex sm:bottom-6 sm:left-6 lg:bottom-10 lg:left-10 lg:bg-[#080b13]/50 lg:backdrop-blur-xl"
                                    data-expert-video-open="{{ e($dialogId) }}"
                                >
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-moto-amber text-black shadow-inner transition-transform duration-300 group-hover/play:scale-105" aria-hidden="true">
                                        <svg class="h-6 w-6 translate-x-[2px]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-[0.65rem] font-bold uppercase tracking-widest text-white/60 group-hover/play:text-moto-amber/80 lg:text-moto-amber/95">Видео</span>
                                        <span class="block text-[14px] font-semibold tracking-wide text-white/95">{{ $videoTrigger }}</span>
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="expert-hero-bg flex min-h-[22rem] items-center justify-center rounded-3xl border border-white/10 lg:min-h-[36rem]">
                        <p class="text-sm font-medium text-white/60">Добавьте фото в блоке Hero в конструкторе страницы.</p>
                    </div>
                @endif
            </div>

            @if(count($badges) > 0)
                <ul class="flex flex-col gap-1.5 border-t border-white/[0.05] pt-3 sm:gap-2.5 sm:pt-5 lg:hidden" aria-label="Коротко о формате">
                    @foreach($badges as $b)
                        @php $t = trim((string) ($b['text'] ?? '')); @endphp
                        @if($t !== '')
                            <li class="inline-flex items-start gap-2.5 text-[13px] font-medium leading-snug text-white/78">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-moto-amber/85" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $t }}
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
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
