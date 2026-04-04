@php
    use App\Support\Storage\TenantPublicAssetResolver;

    $variant = $data['variant'] ?? 'full_background';
    $heading = $data['heading'] ?? '';
    $sub = $data['subheading'] ?? '';
    $btn = $data['button_text'] ?? '';
    $url = $data['button_url'] ?? '#';
    $bgRaw = is_string($data['background_image'] ?? null) ? trim($data['background_image']) : '';
    $bgUrl = $bgRaw !== ''
        ? TenantPublicAssetResolver::resolveForCurrentTenant($bgRaw)
        : theme_platform_asset_url('marketing/hero-bg.png');
    $isCompact = $variant === 'compact';
    $termsDocHero = $isCompact && isset($page) && $page->slug === 'usloviya-arenda';
@endphp
<section class="{{ $isCompact ? 'relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-obsidian to-carbon p-6 ring-1 ring-inset ring-moto-amber/15 sm:p-8 md:p-10 '.($termsDocHero ? 'shadow-lg shadow-black/35' : '') : 'relative overflow-hidden rounded-2xl border border-white/10 bg-carbon/80 p-6 sm:p-10' }}">
    @if(! $isCompact && filled($bgUrl))
        <div class="pointer-events-none absolute inset-0 opacity-30" style="background-image: url('{{ e($bgUrl) }}'); background-size: cover; background-position: center;"></div>
        <div class="pointer-events-none absolute inset-0 bg-black/50"></div>
    @endif
    @if($isCompact)
        <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-moto-amber/10 blur-3xl"></div>
    @endif
    <div class="relative z-10 max-w-3xl">
        @if(filled($heading))
            <h2 class="text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-[2rem] md:leading-snug">{{ $heading }}</h2>
        @endif
        @if(filled($sub))
            <p class="mt-3 text-base leading-relaxed text-silver/90 sm:mt-4 sm:text-lg">{{ $sub }}</p>
        @endif
        @if(filled($btn))
            <a href="{{ e($url) }}" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-moto-amber px-5 py-2.5 text-sm font-semibold text-carbon hover:bg-amber-400 focus-visible:outline focus-visible:ring-2 focus-visible:ring-moto-amber/60 sm:mt-7">{{ $btn }}</a>
        @endif
    </div>
</section>
