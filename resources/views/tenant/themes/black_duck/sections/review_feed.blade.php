@php
    $layout = (string) ($data['layout'] ?? '');
    $isServiceMapsCompact = $layout === 'service_maps_compact';
@endphp
@if(! $isServiceMapsCompact)
    @include('tenant.themes.expert_auto.sections.review_feed', ['section' => $section, 'data' => $data, 'page' => $page ?? null])
@php return; @endphp
@endif
@php
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $reviews = \App\Models\Review::forReviewFeed((int) $tenant->id, $data);
    $h = trim((string) ($data['heading'] ?? ''));
    $sub = trim((string) ($data['subheading'] ?? ''));
    $sectionId = trim((string) ($data['section_id'] ?? ''));
    $link2gis = trim((string) ($data['maps_link_2gis'] ?? ''));
    $linkYandex = trim((string) ($data['maps_link_yandex'] ?? ''));
    $showMapsCta = ! empty($data['show_maps_cta']) && ($link2gis !== '' || $linkYandex !== '');

    $platformLabel = static function (\App\Models\Review $review): string {
        $meta = $review->meta_json;
        $p = is_array($meta) ? trim((string) ($meta['maps_platform'] ?? '')) : '';

        return match ($p) {
            'yandex' => 'Яндекс Карты',
            '2gis' => '2ГИС',
            default => 'Карты',
        };
    };

    $initials = static function (string $name): string {
        $t = trim($name);
        if ($t === '') {
            return '?';
        }
        $parts = preg_split('/\s+/u', $t) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? $t, 0, 1));
    };
@endphp
@if($reviews->isEmpty() && ! $showMapsCta)
    @php return; @endphp
@endif
<section
    @if($sectionId !== '') id="{{ e($sectionId) }}" @endif
    class="scroll-mt-24 border-t border-white/10 pt-10 sm:scroll-mt-28 sm:pt-12"
>
    <div class="max-w-3xl">
        @if($h !== '')
            <h2 class="text-balance text-xl font-bold text-white sm:text-2xl">{{ $h }}</h2>
        @endif
        @if($sub !== '')
            <p class="mt-3 text-pretty text-sm leading-relaxed text-zinc-400 sm:text-base">{{ $sub }}</p>
        @endif
    </div>

    @if($reviews->isNotEmpty())
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-2">
            @foreach($reviews as $review)
                @php
                    $avatar = $review->publicAvatarUrl();
                    $plat = $platformLabel($review);
                    $sectionScope = (int) data_get($section ?? [], 'id', 0);
                @endphp
                <li class="flex min-h-0 gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:p-5">
                    <div class="shrink-0">
                        @if(filled($avatar))
                            <img
                                src="{{ e($avatar) }}"
                                alt=""
                                width="48"
                                height="48"
                                class="h-12 w-12 rounded-full object-cover"
                                loading="lazy"
                                decoding="async"
                                referrerpolicy="no-referrer"
                            />
                        @else
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-sm font-bold text-white" aria-hidden="true">{{ $initials((string) $review->name) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="font-semibold text-white">{{ $review->name }}</span>
                            @if(filled($review->city))
                                <span class="text-xs text-zinc-500">{{ $review->city }}</span>
                            @endif
                        </div>
                        <span class="mt-1 inline-block rounded-md bg-white/[0.06] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ $plat }}</span>
                        @include('tenant.components.review-quote-and-expand', [
                            'review' => $review,
                            'scopeId' => $sectionScope,
                            'quoteClass' => 'mt-3 text-pretty text-sm leading-relaxed text-zinc-300',
                            'openMark' => '«',
                            'closeMark' => '»',
                            'readMoreClass' => 'text-[12px] font-semibold text-zinc-200 underline-offset-4 hover:text-white hover:underline',
                        ])
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @includeWhen($reviews->isNotEmpty(), 'tenant.partials.expert-video-dialog-script')

    @if($showMapsCta)
        <div class="mt-6 rounded-2xl border border-dashed border-white/15 bg-white/[0.02] p-4 sm:p-5">
            <p class="text-sm font-medium text-zinc-300">Ещё отзывы на картах</p>
            <p class="mt-1 text-xs text-zinc-500">Открывается в новой вкладке; на сайте — только выдержки.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @if($link2gis !== '')
                    <a href="{{ e($link2gis) }}" class="inline-flex min-h-10 items-center rounded-xl bg-[#36C7FF]/15 px-4 py-2 text-sm font-semibold text-[#36C7FF] ring-1 ring-[#36C7FF]/30 transition hover:bg-[#36C7FF]/25" target="_blank" rel="nofollow noopener noreferrer">2ГИС — все отзывы</a>
                @endif
                @if($linkYandex !== '')
                    <a href="{{ e($linkYandex) }}" class="inline-flex min-h-10 items-center rounded-xl bg-white/5 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/10" target="_blank" rel="nofollow noopener noreferrer">Яндекс Карты</a>
                @endif
            </div>
        </div>
    @endif
</section>
