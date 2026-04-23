@php
    use App\Support\Storage\TenantPublicAssetResolver;
    use App\Tenant\Expert\ExpertBrandMediaUrl;

    $heading = $data['heading'] ?? '';
    $sub = $data['subheading'] ?? '';
    $btn = $data['button_text'] ?? '';
    $url = $data['button_url'] ?? '#';
    $bgRaw = is_string($data['background_image'] ?? null) ? trim($data['background_image']) : '';
    $bgUrl = $bgRaw !== ''
        ? TenantPublicAssetResolver::resolveForCurrentTenant($bgRaw)
        : theme_platform_asset_url('marketing/hero-bg.png');
    $hasCustomBg = is_string($data['background_image'] ?? null) && trim((string) $data['background_image']) !== '';
    $vSrc = trim((string) ($data['video_src'] ?? ''));
    $vPoster = trim((string) ($data['video_poster'] ?? ''));
    $vSrcUrl = $vSrc !== '' ? ExpertBrandMediaUrl::resolve($vSrc) : '';
    $vPosterUrl = $vPoster !== '' ? ExpertBrandMediaUrl::resolve($vPoster) : '';
@endphp
<section class="relative min-h-[min(52vh,28rem)] overflow-hidden rounded-2xl border border-white/10 bg-carbon/90 sm:min-h-[min(44vh,26rem)]">
    @if (filled($bgUrl))
        <div class="pointer-events-none absolute inset-0">
            @if ($hasCustomBg)
                <img
                    src="{{ e($bgUrl) }}"
                    alt=""
                    class="h-full w-full object-cover"
                    fetchpriority="high"
                    decoding="async"
                />
            @else
                <div class="h-full w-full bg-cover bg-center opacity-40" style="background-image: url('{{ e($bgUrl) }}');" role="img" aria-hidden="true"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/60 to-black/25" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" aria-hidden="true"></div>
        </div>
    @endif
    <div class="relative z-10 max-w-2xl p-6 sm:p-10">
        @if (filled($heading))
            <h1 class="text-balance text-2xl font-bold text-white sm:text-3xl lg:text-4xl">{{ $heading }}</h1>
        @endif
        @if (filled($sub))
            <p class="mt-3 text-pretty text-base leading-relaxed text-zinc-300 sm:text-lg">{{ $sub }}</p>
        @endif
        @if (filled($btn))
            <a href="{{ e($url) }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-[#36C7FF] px-5 py-2.5 text-sm font-semibold text-carbon transition hover:bg-[#5ad2ff]">{{ $btn }}</a>
        @endif
        @if (filled($vSrcUrl) && filled($vPosterUrl))
            <div class="mt-8 w-full max-w-3xl">
                <video
                    class="w-full overflow-hidden rounded-xl border border-white/10"
                    controls
                    playsinline
                    preload="metadata"
                    poster="{{ e($vPosterUrl) }}"
                >
                    <source src="{{ e($vSrcUrl) }}" type="video/mp4" />
                </video>
            </div>
        @endif
    </div>
</section>
