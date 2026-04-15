@php
    use App\Tenant\Expert\ExpertBrandMediaUrl;
    use App\Tenant\Expert\VideoEmbedUrlNormalizer;
    $raw = is_array($data['items'] ?? null) ? $data['items'] : [];
    $items = [];
    foreach ($raw as $row) {
        if (! is_array($row)) {
            continue;
        }
        $mediaKind = trim((string) ($row['media_kind'] ?? ''));
        if ($mediaKind === '') {
            $mediaKind = trim((string) ($row['video_url'] ?? '')) !== '' ? 'video' : 'image';
        }
        $imageUrl = ExpertBrandMediaUrl::resolve(trim((string) ($row['image_url'] ?? '')));
        $videoUrl = ExpertBrandMediaUrl::resolve(trim((string) ($row['video_url'] ?? '')));
        $posterUrl = ExpertBrandMediaUrl::resolve(trim((string) ($row['poster_url'] ?? '')));
        $cap = trim((string) ($row['caption'] ?? ''));
        $sourceUrl = trim((string) ($row['source_url'] ?? ''));
        $sourceLabel = trim((string) ($row['source_label'] ?? ''));
        $sourceNewTab = array_key_exists('source_new_tab', $row)
            ? (bool) $row['source_new_tab']
            : true;
        $embedProvider = trim((string) ($row['embed_provider'] ?? ''));
        $embedShare = trim((string) ($row['embed_share_url'] ?? ''));
        $iframeSrc = null;
        if ($mediaKind === 'video_embed' && $embedProvider !== '' && $embedShare !== '') {
            $iframeSrc = VideoEmbedUrlNormalizer::toIframeSrc($embedProvider, $embedShare);
        }

        if ($mediaKind === 'video_embed') {
            if ($iframeSrc === null) {
                continue;
            }
            $items[] = [
                'kind' => 'embed',
                'image_url' => $imageUrl,
                'video_url' => '',
                'poster_url' => $posterUrl !== '' ? $posterUrl : $imageUrl,
                'iframe_src' => $iframeSrc,
                'caption' => $cap,
                'source_url' => $sourceUrl,
                'source_label' => $sourceLabel,
                'source_new_tab' => $sourceNewTab,
            ];
            continue;
        }
        if ($mediaKind === 'video') {
            if ($videoUrl === '') {
                continue;
            }
            $items[] = [
                'kind' => 'video',
                'image_url' => $imageUrl,
                'video_url' => $videoUrl,
                'poster_url' => $posterUrl !== '' ? $posterUrl : $imageUrl,
                'iframe_src' => '',
                'caption' => $cap,
                'source_url' => $sourceUrl,
                'source_label' => $sourceLabel,
                'source_new_tab' => $sourceNewTab,
            ];
            continue;
        }
        if ($imageUrl === '') {
            continue;
        }
        $items[] = [
            'kind' => 'image',
            'image_url' => $imageUrl,
            'video_url' => '',
            'poster_url' => $posterUrl,
            'iframe_src' => '',
            'caption' => $cap,
            'source_url' => $sourceUrl,
            'source_label' => $sourceLabel,
            'source_new_tab' => $sourceNewTab,
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
                $sourceLinkText = $item['source_label'] !== '' ? $item['source_label'] : 'Читать материал';
            @endphp
            <figure
                class="expert-media-gallery__cell group relative min-w-0 overflow-hidden rounded-xl border border-white/[0.08] bg-white/[0.03] sm:rounded-2xl lg:rounded-[1.5rem] {{ $isHero ? 'col-span-2 row-span-2 min-h-[12rem] sm:min-h-[20rem] lg:min-h-[26rem]' : 'min-h-[8.5rem] sm:min-h-[11rem] lg:min-h-[14rem]' }}"
                @if($i >= 3)
                    x-bind:class="{ 'max-lg:hidden': !galleryMore }"
                @endif
            >
                @if($item['kind'] === 'video' || $item['kind'] === 'embed')
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
                    <figcaption class="pointer-events-none absolute inset-x-0 bottom-0 px-3 pb-3 pt-10 text-left text-[12px] font-semibold leading-snug tracking-wide text-white/95 text-pretty sm:px-5 sm:pb-5 sm:pt-12 sm:text-[15px] drop-shadow-md @if($item['source_url'] !== '') sm:pb-10 @endif">{{ $item['caption'] }}</figcaption>
                @endif
                @if($item['source_url'] !== '')
                    <div class="pointer-events-auto absolute inset-x-0 bottom-2 z-20 px-3 sm:bottom-3 sm:px-5">
                        <a href="{{ e($item['source_url']) }}" class="inline-flex min-h-9 items-center text-[11px] font-bold uppercase tracking-wide text-moto-amber underline decoration-moto-amber/50 underline-offset-2 hover:text-moto-amber/90 sm:text-xs" @if($item['source_new_tab']) target="_blank" rel="noopener noreferrer" @endif>{{ $sourceLinkText }}</a>
                    </div>
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
                        @if($item['kind'] === 'embed')
                            <iframe src="about:blank" data-expert-dialog-embed-src="{{ e($item['iframe_src']) }}" class="expert-video-dialog__embed w-full border-0" title="{{ e($item['caption'] ?: 'Видео') }}" allow="autoplay; encrypted-media; fullscreen; picture-in-picture; screen-wake-lock"></iframe>
                        @elseif($item['kind'] === 'video')
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

@include('tenant.partials.expert-video-dialog-script')
