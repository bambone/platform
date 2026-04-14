@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    $raw = is_array($data['items'] ?? null) ? $data['items'] : [];
    $items = [];
    foreach ($raw as $row) {
        if (! is_array($row)) {
            continue;
        }
        $kind = trim((string) ($row['media_kind'] ?? ''));
        if ($kind === '') {
            $kind = trim((string) ($row['video_url'] ?? '')) !== '' ? 'video' : 'image';
        }
        $imageUrl = ExpertBrandMediaUrl::resolve(trim((string) ($row['image_url'] ?? '')));
        $videoUrl = ExpertBrandMediaUrl::resolve(trim((string) ($row['video_url'] ?? '')));
        $posterUrl = ExpertBrandMediaUrl::resolve(trim((string) ($row['poster_url'] ?? '')));
        $cap = trim((string) ($row['caption'] ?? ''));
        if ($kind === 'video' && $videoUrl === '') {
            continue;
        }
        if ($kind === 'image' && $imageUrl === '' && $cap === '') {
            continue;
        }
        $items[] = [
            'kind' => $kind === 'video' ? 'video' : 'image',
            'image_url' => $imageUrl,
            'video_url' => $videoUrl,
            'poster_url' => $posterUrl !== '' ? $posterUrl : $imageUrl,
            'caption' => $cap,
        ];
    }
    if ($items === []) {
        return;
    }
    $h = trim((string) ($data['section_heading'] ?? ''));
    $lead = trim((string) ($data['section_lead'] ?? ''));
    $sid = (int) data_get($section ?? [], 'id', 0);
@endphp
<section class="expert-media-gallery relative mb-14 min-w-0 sm:mb-20 lg:mb-28" x-data="{ galleryMore: false }">
    <div class="mb-8 min-w-0 sm:mb-10 lg:mb-14">
        @if($h !== '')
            <h2 class="expert-section-title text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold leading-[1.12] tracking-tight text-white/95 sm:leading-[1.1]">{{ $h }}</h2>
        @endif
        @if($lead !== '')
            <p class="mt-4 max-w-3xl text-[15px] font-normal leading-[1.65] text-silver/85 sm:mt-5 sm:text-lg">{{ $lead }}</p>
        @endif
    </div>

    <div class="grid min-w-0 grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-4 lg:gap-6">
        @foreach($items as $i => $item)
            @php
                $isHero = $i === 0;
                $dlgId = 'expert-media-'.$sid.'-'.$i;
            @endphp
            <figure
                class="expert-media-gallery__cell group relative min-w-0 overflow-hidden rounded-xl border border-white/[0.08] bg-white/[0.03] sm:rounded-2xl lg:rounded-[1.5rem] {{ $isHero ? 'col-span-2 row-span-2 min-h-[12rem] sm:min-h-[20rem] lg:min-h-[26rem]' : 'min-h-[8.5rem] sm:min-h-[11rem] lg:min-h-[14rem]' }}"
                @if($i >= 3)
                    x-bind:class="{ 'max-lg:hidden': !galleryMore }"
                @endif
            >
                @if($item['kind'] === 'video')
                    <button type="button" class="relative block h-full w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber/70" data-expert-video-open="{{ e($dlgId) }}" aria-label="{{ e($item['caption'] ?: 'Воспроизвести видео') }}">
                        @if($item['poster_url'] !== '')
                            <img src="{{ e($item['poster_url']) }}" alt="" class="h-full w-full object-cover transition-transform duration-[1.5s] group-hover:scale-105" loading="{{ $isHero ? 'eager' : 'lazy' }}" decoding="async" width="800" height="600">
                        @else
                            <div class="flex h-full min-h-[inherit] w-full items-center justify-center bg-gradient-to-br from-[#12151f] to-[#070910]"></div>
                        @endif
                        <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#050608]/90 via-[#050608]/10 to-transparent transition-opacity duration-300 group-hover:opacity-80"></span>
                        <span class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white shadow-[0_8px_32px_-8px_rgba(0,0,0,0.5)] ring-1 ring-inset ring-white/30 backdrop-blur-md transition-all duration-300 group-hover:bg-moto-amber group-hover:text-black group-hover:ring-moto-amber/50 sm:h-20 sm:w-20" aria-hidden="true">
                            <svg class="h-7 w-7 translate-x-[2px] sm:h-10 sm:w-10" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                        </span>
                    </button>
                @elseif($item['image_url'] !== '')
                    <button type="button" class="group/img relative block h-full w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber/70" data-expert-video-open="{{ e($dlgId) }}" aria-label="{{ e($item['caption'] ?: 'Открыть фото') }}">
                        <img src="{{ e($item['image_url']) }}" alt="{{ e($item['caption'] ?: 'Фото с занятий') }}" class="h-full w-full object-cover transition-transform duration-[1.5s] group-hover/img:scale-105 {{ $isHero ? 'aspect-[16/11]' : 'aspect-[4/3]' }}" loading="{{ $isHero ? 'eager' : 'lazy' }}" decoding="async" width="800" height="600">
                        <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#050608]/80 via-transparent to-transparent opacity-60 transition-opacity duration-300 group-hover/img:opacity-100"></span>
                    </button>
                @endif
                @if($item['caption'] !== '')
                    <figcaption class="pointer-events-none absolute inset-x-0 bottom-0 px-3 pb-3 pt-10 text-left text-[12px] font-semibold leading-snug tracking-wide text-white/95 text-pretty sm:px-5 sm:pb-5 sm:pt-12 sm:text-[15px] drop-shadow-md">{{ $item['caption'] }}</figcaption>
                @endif
            </figure>

            <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="{{ e($item['caption'] ?: 'Медиа') }}">
                <div class="expert-video-dialog__panel">
                    <div class="expert-video-dialog__head">
                        <p class="truncate pr-4 text-sm font-semibold text-white">{{ $item['caption'] !== '' ? $item['caption'] : 'Просмотр' }}</p>
                        <form method="dialog">
                            <button type="submit" class="expert-video-dialog__close shrink-0 rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                        </form>
                    </div>
                    <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                        @if($item['kind'] === 'video')
                            <video class="expert-video-dialog__video" controls playsinline preload="none" @if($item['poster_url'] !== '') poster="{{ e($item['poster_url']) }}" @endif data-expert-dialog-src="{{ e($item['video_url']) }}"></video>
                        @else
                            <img src="{{ e($item['image_url']) }}" alt="{{ e($item['caption'] ?: 'Фото') }}" class="max-h-[min(78vh,900px)] w-full object-contain">
                        @endif
                    </div>
                </div>
            </dialog>
        @endforeach
        @if(count($items) > 3)
            <div class="col-span-2 flex justify-center pt-2 sm:col-span-4 lg:hidden">
                <button type="button" class="min-h-11 rounded-full border border-white/12 bg-white/[0.05] px-5 py-2 text-sm font-semibold text-white/90" @click="galleryMore = !galleryMore" x-text="galleryMore ? 'Свернуть' : 'Ещё фото и видео'"></button>
            </div>
        @endif
    </div>
</section>

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
