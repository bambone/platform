@php
    use App\Support\Storage\TenantPublicAssetResolver;

    $heading = $data['heading'] ?? '';
    $sub = $data['subheading'] ?? '';
    $btn = $data['button_text'] ?? '';
    $url = $data['button_url'] ?? '#';
    $bgRaw = is_string($data['background_image'] ?? null) ? trim($data['background_image']) : '';
    $bgUrl = $bgRaw !== ''
        ? TenantPublicAssetResolver::resolveForCurrentTenant($bgRaw)
        : theme_platform_asset_url('marketing/hero-bg.png');
@endphp
<section class="relative overflow-hidden rounded-2xl border border-white/10 bg-carbon/80 p-6 sm:p-10">
    @if(filled($bgUrl))
        <div class="pointer-events-none absolute inset-0 opacity-30" style="background-image: url('{{ e($bgUrl) }}'); background-size: cover; background-position: center;"></div>
        <div class="pointer-events-none absolute inset-0 bg-black/50"></div>
    @endif
    <div class="relative z-10 max-w-2xl">
        @if(filled($heading))
            <h2 class="text-balance text-2xl font-bold text-white sm:text-3xl">{{ $heading }}</h2>
        @endif
        @if(filled($sub))
            <p class="mt-3 text-silver">{{ $sub }}</p>
        @endif
        @if(filled($btn))
            <a href="{{ e($url) }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-carbon hover:bg-amber-400">{{ $btn }}</a>
        @endif
    </div>
</section>
