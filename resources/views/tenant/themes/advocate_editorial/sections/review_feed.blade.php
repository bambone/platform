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
    $sid = (int) data_get($section ?? [], 'id', 0);

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
<section @if($sectionId !== '') id="{{ e($sectionId) }}" @endif class="expert-reviews-mega relative mb-14 min-w-0 scroll-mt-24 sm:mb-20 sm:scroll-mt-28 lg:mb-28" x-data="{ reviewsMore: false }">
    <div class="mb-8 max-w-3xl min-w-0 sm:mb-10 lg:mb-14">
        @if($h !== '')
            <h2 class="expert-section-title text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold leading-[1.12] tracking-tight text-white/95 sm:leading-[1.1]">{{ $h }}</h2>
        @endif
        @if($sub !== '')
            <p class="mt-5 text-[15px] font-normal leading-[1.6] text-silver/85 sm:text-lg">{{ $sub }}</p>
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
        <div class="expert-reviews-spotlight mb-8 grid min-w-0 gap-4 sm:mb-10 sm:gap-6 lg:mb-12 lg:grid-cols-12 lg:items-stretch lg:gap-8">
            @php
                $review = $heroReview;
                $vUrl = trim((string) ($review->video_url ?? ''));
                $isVideo = ($review->media_type ?? 'text') === 'video' && $vUrl !== '';
                $dlgId = 'expert-review-vid-'.$review->id.'-'.$sid;
                $useModal = $isVideo && $isDirectVideo($vUrl);
                $ck = $review->category_key ?? null;
                $tag = $catLabel(is_string($ck) ? $ck : null);
            @endphp
            <article class="expert-review expert-review--spotlight relative flex min-h-full min-w-0 flex-col overflow-hidden rounded-[1.35rem] border border-moto-amber/30 bg-gradient-to-br from-[#12141c] to-[#0a0c12] p-6 shadow-[0_28px_64px_-20px_rgba(0,0,0,0.72)] ring-1 ring-inset ring-white/[0.06] sm:rounded-[2rem] sm:p-10 {{ $sideFeatured->isEmpty() ? 'lg:col-span-12' : 'lg:col-span-7' }}">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/[0.04] via-transparent to-transparent"></div>
                <div class="relative z-10 flex flex-col h-full">
                    <span class="mb-6 inline-flex w-fit items-center gap-2 rounded-full bg-moto-amber/10 px-3 py-1.5 text-[0.65rem] font-bold uppercase tracking-widest text-moto-amber ring-1 ring-inset ring-moto-amber/30">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        Выбор эксперта
                    </span>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-2xl font-bold text-white/95 sm:text-3xl">{{ $review->name }}</p>
                            @if(filled($review->headline))
                                <p class="mt-2 text-[15px] font-semibold text-moto-amber/90 sm:text-lg">{{ $review->headline }}</p>
                            @endif
                        </div>
                        @if(filled($review->city))
                            <span class="shrink-0 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-silver/80">{{ $review->city }}</span>
                        @endif
                    </div>
                    @if($tag)
                        <span class="mt-4 inline-flex w-fit rounded-lg border border-white/[0.06] bg-white/[0.02] px-2.5 py-1 text-[10px] font-bold uppercase tracking-widest text-silver/70">{{ $tag }}</span>
                    @endif
                    
                    <p class="expert-review__quote mt-8 flex-1 text-[17px] font-medium leading-[1.7] text-white/90 sm:text-[19px]">"{{ $review->display_body }}"</p>
                    
                    <div class="mt-6 flex flex-col gap-4 border-t border-white/[0.06] pt-6 sm:mt-8 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
                        <div class="expert-review__stars flex gap-1 text-moto-amber" aria-hidden="true">
                            @for($i = 0; $i < min(5, (int) $review->rating); $i++)
                                <svg class="h-5 w-5 drop-shadow-[0_1px_2px_rgba(0,0,0,0.35)]" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                            @endfor
                        </div>
                        <span class="sr-only">Оценка {{ (int) $review->rating }} из 5</span>
                        
                        @if($isVideo)
                            @if($useModal)
                                <button type="button" class="group inline-flex min-h-11 w-full items-center justify-center gap-2.5 rounded-xl border border-moto-amber/30 bg-moto-amber/10 px-4 py-2.5 text-[13px] font-bold uppercase tracking-wide text-moto-amber transition hover:bg-moto-amber/20 sm:min-h-0 sm:w-auto sm:justify-start" data-expert-video-open="{{ e($dlgId) }}">
                                    <svg class="h-4 w-4 transition-transform group-hover:scale-110" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                    Видео-отзыв
                                </button>
                                <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="Видео-отзыв">
                                    <div class="expert-video-dialog__panel">
                                        <div class="expert-video-dialog__head">
                                            <p class="truncate text-sm font-semibold text-white">Отзыв: {{ $review->name }}</p>
                                            <form method="dialog">
                                                <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                                            </form>
                                        </div>
                                        <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                                            <video class="expert-video-dialog__video" controls playsinline preload="none" data-expert-dialog-src="{{ e($vUrl) }}"></video>
                                        </div>
                                    </div>
                                </dialog>
                            @else
                                <a href="{{ e($vUrl) }}" class="inline-flex items-center gap-2 text-[13px] font-bold uppercase tracking-wide text-moto-amber transition hover:text-moto-amber/80" target="_blank" rel="noopener noreferrer">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                    Смотреть
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            </article>
            @if($sideFeatured->isNotEmpty())
                <div class="flex min-w-0 flex-col gap-3 sm:gap-4 lg:col-span-5">
                    @foreach($sideFeatured as $review)
                        @php
                            $vUrl = trim((string) ($review->video_url ?? ''));
                            $isVideo = ($review->media_type ?? 'text') === 'video' && $vUrl !== '';
                            $dlgId = 'expert-review-vid-'.$review->id.'-'.$sid;
                            $useModal = $isVideo && $isDirectVideo($vUrl);
                            $ck = $review->category_key ?? null;
                            $tag = $catLabel(is_string($ck) ? $ck : null);
                        @endphp
                        <article class="expert-review expert-review--side flex flex-1 flex-col rounded-[1.5rem] border border-white/[0.08] bg-white/[0.015] p-6 lg:p-8 backdrop-blur-sm transition hover:bg-white/[0.03]">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-lg font-bold text-white/95">{{ $review->name }}</p>
                                    @if(filled($review->headline))
                                        <p class="mt-1 text-[13px] font-semibold text-moto-amber/80">{{ $review->headline }}</p>
                                    @endif
                                </div>
                                @if($tag)
                                    <span class="shrink-0 rounded-lg border border-white/[0.06] bg-white/[0.02] px-2.5 py-1 text-[10px] font-bold uppercase tracking-widest text-silver/70">{{ $tag }}</span>
                                @endif
                            </div>
                            <p class="expert-review__quote mt-5 flex-1 text-[14px] leading-[1.65] text-white/80">"{{ $review->display_body }}"</p>
                            
                            <div class="mt-6 flex items-center justify-between border-t border-white/[0.04] pt-4">
                                <div class="expert-review__stars flex gap-0.5 text-moto-amber/60" aria-hidden="true">
                                    @for($i = 0; $i < min(5, (int) $review->rating); $i++)
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                    @endfor
                                </div>
                                @if($isVideo)
                                    @if($useModal)
                                        <button type="button" class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-moto-amber/80 transition hover:text-moto-amber" data-expert-video-open="{{ e($dlgId) }}">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                            Видео
                                        </button>
                                        <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="Видео-отзыв">
                                            <div class="expert-video-dialog__panel">
                                                <div class="expert-video-dialog__head">
                                                    <p class="truncate text-sm font-semibold text-white">{{ $review->name }}</p>
                                                    <form method="dialog">
                                                        <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                                                    </form>
                                                </div>
                                                <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                                                    <video class="expert-video-dialog__video" controls playsinline preload="none" data-expert-dialog-src="{{ e($vUrl) }}"></video>
                                                </div>
                                            </div>
                                        </dialog>
                                    @else
                                        <a href="{{ e($vUrl) }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-moto-amber/80 transition hover:text-moto-amber" target="_blank" rel="noopener noreferrer">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                            Видео
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="grid min-w-0 gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-3">
        @php $reviewGridIdx = 0; @endphp
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
            <article
                class="expert-review expert-review--grid flex min-w-0 flex-col rounded-[1.35rem] border border-white/[0.05] bg-white/[0.015] p-4 backdrop-blur-sm transition hover:border-white/[0.08] hover:bg-white/[0.03] sm:rounded-[1.5rem] sm:p-6 lg:p-8"
                @if($reviewGridIdx >= 2)
                    x-bind:class="{ 'max-lg:hidden': !reviewsMore }"
                @endif
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-white/95">{{ $review->name }}</p>
                        @if(filled($review->headline))
                            <p class="mt-1 text-[13px] font-semibold text-moto-amber/70">{{ $review->headline }}</p>
                        @endif
                    </div>
                    @if(filled($review->city))
                        <span class="shrink-0 text-[11px] font-bold uppercase tracking-widest text-silver/50">{{ $review->city }}</span>
                    @endif
                </div>
                @if($tag)
                    <span class="mt-3 inline-flex w-fit rounded-lg border border-white/[0.04] bg-white/[0.01] px-2.5 py-1 text-[10px] font-bold uppercase tracking-widest text-silver/60">{{ $tag }}</span>
                @endif
                
                <p class="expert-review__quote mt-5 flex-1 text-[14px] leading-relaxed text-silver/85">"{{ $review->display_body }}"</p>
                
                <div class="mt-6 flex items-center justify-between border-t border-white/[0.03] pt-4">
                    <div class="expert-review__stars flex gap-0.5 text-moto-amber/40" aria-hidden="true">
                        @for($i = 0; $i < min(5, (int) $review->rating); $i++)
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        @endfor
                    </div>
                    @if($isVideo)
                        @if($useModal)
                            <button type="button" class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-moto-amber/70 transition hover:text-moto-amber" data-expert-video-open="{{ e($dlgId) }}">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                Видео
                            </button>
                            <dialog id="{{ e($dlgId) }}" class="expert-video-dialog expert-video-dialog--wide" aria-label="Видео-отзыв">
                                <div class="expert-video-dialog__panel">
                                    <div class="expert-video-dialog__head">
                                        <p class="truncate text-sm font-semibold text-white">{{ $review->name }}</p>
                                        <form method="dialog">
                                            <button type="submit" class="expert-video-dialog__close rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10">Закрыть</button>
                                        </form>
                                    </div>
                                    <div class="expert-video-dialog__body expert-video-dialog__body--flush">
                                        <video class="expert-video-dialog__video" controls playsinline preload="none" data-expert-dialog-src="{{ e($vUrl) }}"></video>
                                    </div>
                                </div>
                            </dialog>
                        @else
                            <a href="{{ e($vUrl) }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-moto-amber/70 transition hover:text-moto-amber" target="_blank" rel="noopener noreferrer">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                Видео
                            </a>
                        @endif
                    @endif
                </div>
            </article>
            @php $reviewGridIdx++; @endphp
        @endforeach
        @if($reviewGridIdx > 2)
            <div class="col-span-full flex justify-center pt-1 md:col-span-2 xl:col-span-3 lg:hidden">
                <button type="button" class="min-h-11 rounded-full border border-white/12 bg-white/[0.04] px-5 py-2 text-sm font-semibold text-white/90" @click="reviewsMore = !reviewsMore" x-text="reviewsMore ? 'Свернуть' : 'Все отзывы'"></button>
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
