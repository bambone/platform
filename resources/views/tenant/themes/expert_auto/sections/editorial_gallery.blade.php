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
    $sid = (int) ($section->id ?? 0);
@endphp
<section class="expert-media-gallery mb-16 sm:mb-24">
    <div class="mb-10 max-w-3xl lg:mb-12">
        @if($h !== '')
            <h2 class="text-balance text-[clamp(1.5rem,3.5vw,2.25rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
        @endif
        @if($lead !== '')
            <p class="mt-4 text-base leading-relaxed text-silver sm:text-lg">{{ $lead }}</p>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        @foreach($items as $i => $item)
            @php
                $isHero = $i === 0;
                $dlgId = 'expert-media-'.$sid.'-'.$i;
            @endphp
            <figure class="expert-media-gallery__cell group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] {{ $isHero ? 'col-span-2 row-span-2 min-h-[14rem] sm:min-h-[18rem]' : 'min-h-[9rem] sm:min-h-[11rem]' }}">
                @if($item['kind'] === 'video')
                    <button type="button" class="relative block h-full w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber/70" data-expert-video-open="{{ e($dlgId) }}" aria-label="{{ e($item['caption'] ?: 'Воспроизвести видео') }}">
                        @if($item['poster_url'] !== '')
                            <img src="{{ e($item['poster_url']) }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="{{ $isHero ? 'eager' : 'lazy' }}" decoding="async" width="800" height="600">
                        @else
                            <div class="flex h-full min-h-[inherit] w-full items-center justify-center bg-gradient-to-br from-[#12151f] to-[#070910]"></div>
                        @endif
                        <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#05070f]/90 via-[#05070f]/20 to-transparent"></span>
                        <span class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-moto-amber/90 text-[#0a0c12] shadow-lg ring-2 ring-white/20" aria-hidden="true">
                            <svg class="h-6 w-6 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M8 5v14l11-7L8 5z"/></svg>
                        </span>
                    </button>
                @elseif($item['image_url'] !== '')
                    <button type="button" class="block h-full w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber/70" data-expert-video-open="{{ e($dlgId) }}" aria-label="{{ e($item['caption'] ?: 'Открыть фото') }}">
                        <img src="{{ e($item['image_url']) }}" alt="{{ e($item['caption'] ?: 'Фото с занятий') }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03] {{ $isHero ? 'aspect-[16/11] min-h-[12rem] sm:min-h-[16rem]' : 'aspect-[4/3]' }}" loading="{{ $isHero ? 'eager' : 'lazy' }}" decoding="async" width="800" height="600">
                    </button>
                @endif
                @if($item['caption'] !== '')
                    <figcaption class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#05070f] to-transparent px-3 pb-3 pt-10 text-xs font-medium text-white/90 sm:text-sm">{{ $item['caption'] }}</figcaption>
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
                            <video class="expert-video-dialog__video" controls playsinline preload="metadata" @if($item['poster_url'] !== '') poster="{{ e($item['poster_url']) }}" @endif src="{{ e($item['video_url']) }}"></video>
                        @else
                            <img src="{{ e($item['image_url']) }}" alt="{{ e($item['caption'] ?: 'Фото') }}" class="max-h-[min(78vh,900px)] w-full object-contain">
                        @endif
                    </div>
                </div>
            </dialog>
        @endforeach
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
