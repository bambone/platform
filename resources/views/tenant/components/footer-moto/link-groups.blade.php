@php
    $meta = $block['meta'] ?? [];
    $lg = $block['link_groups'] ?? [];
    $expertPrLinkGrid = tenant()?->themeKey() === 'expert_pr' && count($lg) >= 2;
    $sectionHeading = filled($block['title'] ?? '')
        ? ($block['title'] ?? '')
        : (string) ($meta['headline'] ?? '');
@endphp
@if(filled($sectionHeading))
    <h3 class="mb-4 text-lg font-semibold text-white">{{ $sectionHeading }}</h3>
@endif
<div @class([
    'tenant-footer-link-groups',
    'lg:grid lg:grid-cols-2 lg:gap-x-10 lg:gap-y-8 xl:grid-cols-3' => $expertPrLinkGrid,
])>
@foreach($lg as $group)
    <nav class="mb-6 border-t border-white/[0.06] pt-6 first:border-t-0 first:pt-0 lg:mb-0 lg:border-t-0 lg:pt-0" aria-label="{{ $group['title'] ?? (tenant()?->themeKey() === 'expert_pr' ? 'Links' : 'Ссылки') }}">
        @if(filled($group['title'] ?? ''))
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-white/45">{{ $group['title'] }}</p>
        @endif
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-[13px] text-white/70">
            @foreach($group['links'] ?? [] as $ln)
                <a href="{{ $ln['href'] }}" @if(($ln['target'] ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif class="inline-flex min-h-9 items-center underline-offset-4 transition hover:text-moto-amber hover:underline">{{ $ln['label'] }}</a>
            @endforeach
        </div>
    </nav>
@endforeach
</div>
