@php
    /** @var array $footer from {@see \App\Tenant\Footer\TenantFooterResolver} */
    $f = $footer;
    $mode = $f['mode'] ?? 'minimal';
    $sections = $f['sections'] ?? [];
    $siteName = $f['site_name'] ?? ($site_name ?? '');
    $year = $f['year'] ?? (int) now()->year;
@endphp
<footer class="tenant-site-footer-moto relative z-10 mt-6 w-full min-w-0 sm:mt-8 @if($mode === 'minimal') border-t border-moto-amber/20 @else border-t border-white/10 @endif" role="contentinfo" aria-labelledby="tenant-moto-footer-heading">
    <div class="mx-auto w-full min-w-0 max-w-7xl @if($mode === 'minimal') px-0 pb-0 @else px-3 pb-8 sm:px-4 md:px-8 lg:pb-10 pt-8 lg:pt-10 @endif">
        <h2 id="tenant-moto-footer-heading" class="sr-only">{{ tenant()?->themeKey() === 'expert_pr' ? 'Site footer' : 'Подвал сайта' }}</h2>

        @if($mode === 'minimal')
            @include('tenant.components.footer-moto.minimal', ['f' => $f])
        @else
            @foreach($sections as $block)
                @php
                    $type = $block['type'] ?? '';
                @endphp
                @if($type === 'cta_strip')
                    @include('tenant.components.footer-moto.cta-strip', ['block' => $block])
                @elseif($type === 'contacts')
                    @include('tenant.components.footer-moto.contacts', ['block' => $block])
                @elseif($type === 'geo_points')
                    @include('tenant.components.footer-moto.geo-points', ['block' => $block])
                @elseif($type === 'conditions_list')
                    @include('tenant.components.footer-moto.conditions-list', ['block' => $block])
                @elseif($type === 'link_groups')
                    @include('tenant.components.footer-moto.link-groups', ['block' => $block])
                @elseif($type === 'bottom_bar')
                    @include('tenant.components.footer-moto.bottom-bar', ['f' => $f, 'block' => $block, 'year' => $year, 'siteName' => $siteName])
                @endif
            @endforeach
        @endif
    </div>
</footer>
