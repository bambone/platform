@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $reviews = \App\Models\Review::forReviewFeed((int) $tenant->id, $data);
    if ($reviews->isEmpty()) {
        return;
    }
    $h = trim((string) ($data['heading'] ?? ''));
    $sub = trim((string) ($data['subheading'] ?? ''));
    $sectionId = trim((string) ($data['section_id'] ?? ''));
    $sid = (int) ($section->id ?? 0);

    $catLabel = static function (?string $key): ?string {
        return match ($key) {
            'parking' => 'Парковка',
            'city', 'city-driving' => 'Город',
            'counter-emergency', 'winter-driving' => 'Контраварийка',
            'winter' => 'Зима',
            'confidence' => 'Уверенность',
            'motorsport' => 'Автоспорт',
            default => null,
        };
    };

    $isDirectVideo = static function (?string $url): bool {
        if ($url === null || $url === '') {
            return false;
        }
        $u = strtolower($url);

        return str_contains($u, '.mp4') || str_contains($u, '.webm') || str_contains($u, 'video/');
    };
@endphp
<section @if($sectionId !== '') id="{{ e($sectionId) }}" @endif class="expert-reviews-mega mb-16 scroll-mt-24 sm:mb-24 sm:scroll-mt-28">
    <div class="mb-10 max-w-3xl lg:mb-12">
        @if($h !== '')
            <h2 class="text-balance text-[clamp(1.5rem,3.5vw,2.35rem)] font-bold tracking-tight text-white">{{ $h }}</h2>
        @endif
        @if($sub !== '')
            <p class="mt-4 text-base leading-relaxed text-silver sm:text-lg">{{ $sub }}</p>
        @endif
    </div>

    @php
        $featOrdered = $reviews->where('is_featured', true)->sortBy('sort_order')->values();
        $heroReview = $featOrdered->first();
        $sideFeatured = $featOrdered->slice(1, 2)->values();
        $spotlightIds = [];
        if ($heroReview !== null) {
            $spotlightIds[] = $heroReview->id;
        }
        foreach ($sideFeatured as $sf) {
            $spotlightIds[] = $sf->id;
        }
    @endphp

    @if($heroReview !== null)
        <div class="expert-reviews-spotlight mb-10 grid gap-6 lg:mb-12 lg:grid-cols-12 lg:items-stretch lg:gap-8">
            @php
                $review = $heroReview;
                $vUrl = trim((string) ($review->video_url ?? ''));
                $isVideo = ($review->media_type ?? 'text') === 'video' && $vUrl !== '';
                $dlgId = 'expert-review-vid-'.$review->id.'-'.$sid;
                $useModal = $isVideo && $isDirectVideo($vUrl);
                $ck = $review->category_key ?? null;
                $tag = $catLabel(is_string($ck) ? $ck : null);
            @endphp
            <article class="expert-review expert-review--spotlight flex min-h-full flex-col overflow-hidden rounded-2xl border border-moto-amber/35 bg-gradient-to-br from-moto-amber/[0.12] to-white/[0.04] p-6 shadow-[0_28px_70px_-34px_rgba(201,168,124,0.35)] sm:p-8 {{ $sideFeatured->isEmpty() ? 'lg:col-span-12' : 'lg:col-span-7' }}">
                <span class="mb-4 inline-flex w-fit rounded-full bg-moto-amber/22 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-moto-amber">Сильный отзыв</span>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xl font-bold text-white sm:text-2xl">{{ $review->name }}</p>
                        @if(filled($review->headline))
                            <p class="mt-2 text-base font-semibold text-moto-amber">{{ $review->headline }}</p>
                        @endif
                    </div>
                    @if(filled($review->city))
                        <span class="shrink-0 rounded-full border border-white/12 bg-white/5 px-3 py-1 text-xs text-silver">{{ $review->city }}</span>
                    @endif
                </div>
                @if($tag)
                    <span class="mt-3 inline-flex w-fit rounded-md border border-white/10 bg-white/5 px-2 py-0.5 text-[0.7rem] font-semibold uppercase tracking-wide text-silver">{{ $tag }}</span>
                @endif
                <p class="expert-review__quote mt-6 flex-1 text-lg leading-relaxed text-white/92 sm:text-xl">{{ $review->display_body }}</p>
                <div class="expert-review__stars mt-5 flex gap-0.5 text-moto-amber/45" aria-hidden="true">
                    @for($i = 0; $i < min(5, (int) $review->rating); $i++)
                        <span class="text-lg leading-none">★</span>
                    @endfor
                </div>
                <span class="sr-only">Оценка {{ (int) $review->rating }} из 5</span>
                @if($isVideo)
                    @if($useModal)
                        <button type="button" class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-xl border border-moto-amber/35 bg-moto-amber/10 px-4 py-2 text-sm font-semibold text-moto-amber transition hover:bg-moto-amber/16" data-expert-video-open="{{ e($dlgId) }}">Видео-отзыв</button>
                        <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="Видео-отзыв">
                            <div class="expert-video-dialog__panel">
                                <div class="expert-video-dialog__head">
                                    <p class="truncate text-sm font-semibold text-white">Отзыв: {{ $review->name }}</p>
                                    <form method="dialog">
                                        <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                                    </form>
                                </div>
                                <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                                    <video class="expert-video-dialog__video" controls playsinline preload="metadata" src="{{ e($vUrl) }}"></video>
                                </div>
                            </div>
                        </dialog>
                    @else
                        <a href="{{ e($vUrl) }}" class="mt-5 inline-flex text-sm font-semibold text-moto-amber underline-offset-2 hover:underline" target="_blank" rel="noopener noreferrer">Смотреть видео-отзыв</a>
                    @endif
                @endif
            </article>
            @if($sideFeatured->isNotEmpty())
                <div class="flex flex-col gap-4 lg:col-span-5">
                    @foreach($sideFeatured as $review)
                        @php
                            $vUrl = trim((string) ($review->video_url ?? ''));
                            $isVideo = ($review->media_type ?? 'text') === 'video' && $vUrl !== '';
                            $dlgId = 'expert-review-vid-'.$review->id.'-'.$sid;
                            $useModal = $isVideo && $isDirectVideo($vUrl);
                            $ck = $review->category_key ?? null;
                            $tag = $catLabel(is_string($ck) ? $ck : null);
                        @endphp
                        <article class="expert-review expert-review--side flex flex-1 flex-col rounded-2xl border border-white/12 bg-[#0c1018]/90 p-5 backdrop-blur-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-bold text-white">{{ $review->name }}</p>
                                    @if(filled($review->headline))
                                        <p class="mt-1 text-sm font-medium text-moto-amber">{{ $review->headline }}</p>
                                    @endif
                                </div>
                                @if($tag)
                                    <span class="shrink-0 rounded-md border border-white/10 bg-white/5 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-silver">{{ $tag }}</span>
                                @endif
                            </div>
                            <p class="expert-review__quote mt-3 flex-1 text-sm leading-relaxed text-white/88">{{ $review->display_body }}</p>
                            <div class="expert-review__stars mt-3 flex gap-0.5 text-moto-amber/30" aria-hidden="true">
                                @for($i = 0; $i < min(5, (int) $review->rating); $i++)
                                    <span class="text-sm leading-none">★</span>
                                @endfor
                            </div>
                            @if($isVideo)
                                @if($useModal)
                                    <button type="button" class="mt-3 text-left text-sm font-medium text-moto-amber underline-offset-2 hover:underline" data-expert-video-open="{{ e($dlgId) }}">Видео</button>
                                    <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="Видео-отзыв">
                                        <div class="expert-video-dialog__panel">
                                            <div class="expert-video-dialog__head">
                                                <p class="truncate text-sm font-semibold text-white">{{ $review->name }}</p>
                                                <form method="dialog">
                                                    <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                                                </form>
                                            </div>
                                            <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                                                <video class="expert-video-dialog__video" controls playsinline preload="metadata" src="{{ e($vUrl) }}"></video>
                                            </div>
                                        </div>
                                    </dialog>
                                @else
                                    <a href="{{ e($vUrl) }}" class="mt-3 text-sm font-medium text-moto-amber underline-offset-2 hover:underline" target="_blank" rel="noopener noreferrer">Видео</a>
                                @endif
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($reviews as $review)
            @if(in_array($review->id, $spotlightIds, true))
                @continue
            @endif
            @php
                $vUrl = trim((string) ($review->video_url ?? ''));
                $isVideo = ($review->media_type ?? 'text') === 'video' && $vUrl !== '';
                $dlgId = 'expert-review-vid-'.$review->id.'-'.$sid;
                $useModal = $isVideo && $isDirectVideo($vUrl);
                $ck = $review->category_key ?? null;
                $tag = $catLabel(is_string($ck) ? $ck : null);
            @endphp
            <article class="expert-review expert-review--grid flex flex-col rounded-2xl border border-white/10 bg-[#0b0d14]/80 p-6 backdrop-blur-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-white">{{ $review->name }}</p>
                        @if(filled($review->headline))
                            <p class="mt-1 text-sm font-medium text-moto-amber">{{ $review->headline }}</p>
                        @endif
                    </div>
                    @if(filled($review->city))
                        <span class="shrink-0 text-xs text-silver">{{ $review->city }}</span>
                    @endif
                </div>
                @if($tag)
                    <span class="mt-2 inline-flex w-fit rounded-md border border-white/8 bg-white/[0.04] px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-silver/90">{{ $tag }}</span>
                @endif
                <p class="mt-4 flex-1 text-sm leading-relaxed text-silver sm:text-base">{{ $review->display_body }}</p>
                <div class="expert-review__stars mt-4 flex gap-0.5 text-moto-amber/30" aria-hidden="true">
                    @for($i = 0; $i < min(5, (int) $review->rating); $i++)
                        <span class="text-sm">★</span>
                    @endfor
                </div>
                @if($isVideo)
                    @if($useModal)
                        <button type="button" class="mt-3 inline-flex text-sm font-medium text-moto-amber underline-offset-2 hover:underline" data-expert-video-open="{{ e($dlgId) }}">Видео</button>
                        <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="Видео-отзыв">
                            <div class="expert-video-dialog__panel">
                                <div class="expert-video-dialog__head">
                                    <p class="truncate text-sm font-semibold text-white">{{ $review->name }}</p>
                                    <form method="dialog">
                                        <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                                    </form>
                                </div>
                                <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                                    <video class="expert-video-dialog__video" controls playsinline preload="metadata" src="{{ e($vUrl) }}"></video>
                                </div>
                            </div>
                        </dialog>
                    @else
                        <a href="{{ e($vUrl) }}" class="mt-3 inline-flex text-sm font-medium text-moto-amber underline-offset-2 hover:underline" target="_blank" rel="noopener noreferrer">Видео</a>
                    @endif
                @endif
            </article>
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
