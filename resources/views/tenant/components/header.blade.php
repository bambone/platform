@php
    $isExpertAuto = tenant()?->themeKey() === 'expert_auto';
    $headerBrandTitle = $site_name ?? config('app.name');
    if ($isExpertAuto && is_string($headerBrandTitle)) {
        $parts = preg_split('/\s*[—–]\s*/u', $headerBrandTitle, 2);
        $headerBrandTitle = trim((string) ($parts[0] ?? $headerBrandTitle));
        if ($headerBrandTitle === '') {
            $headerBrandTitle = 'Марат Афлятунов';
        }
    }
@endphp
<header x-data="tenantHeader()"
        x-init="init()"
        @keydown.escape.window="mobileNavOpen = false"
        @hero-video-playing.window="videoPlaying = true"
        @hero-video-stopped.window="videoPlaying = false"
        :class="(videoPlaying || compact) ? 'bg-obsidian/85 backdrop-blur-md shadow-sm' : 'bg-gradient-to-b from-black/60 to-transparent'"
        class="fixed top-0 left-0 right-0 z-50 flex h-16 flex-col md:h-[4.5rem] transition-colors duration-300">
    <div class="relative flex h-16 w-full shrink-0 items-center md:h-[4.5rem]">
        @if($isExpertAuto)
        {{-- Три зоны: бренд | навигация | телефон; короткое имя в шапке — без обрезки длинного слогана --}}
        <div class="expert-header-bar mx-auto grid h-full w-full max-w-[min(92rem,calc(100vw-0.75rem))] grid-cols-[minmax(0,1fr)_auto] items-center gap-2 px-3 sm:px-4 md:grid-cols-[minmax(0,max-content)_minmax(0,1fr)_auto] md:gap-3 md:px-5 lg:gap-5 lg:px-8">
            <a href="{{ route('home') }}" class="expert-header-bar__brand group relative flex min-w-0 max-w-full items-center gap-2.5 sm:gap-3" style="text-shadow: 0 2px 10px rgba(0,0,0,0.6);">
                <div class="pointer-events-none absolute inset-0 rounded-xl bg-white/[0.03] opacity-0 transition duration-300 group-hover:opacity-100"></div>
                @if(($branding['logo'] ?? null))
                    <img src="{{ $branding['logo'] }}" alt="{{ $headerBrandTitle }}"
                         width="96" height="96"
                         loading="eager"
                         decoding="async"
                         class="relative h-10 w-10 shrink-0 rounded-full object-contain drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)] sm:h-11 sm:w-11" />
                @else
                    @include('tenant.components.expert-brand-mark', ['compact' => true])
                @endif
                <span class="min-w-0 text-balance text-base font-bold leading-snug tracking-tight text-white sm:text-lg lg:text-xl">{{ $headerBrandTitle }}</span>
            </a>

            <nav class="expert-header-bar__nav hidden min-w-0 items-center justify-center gap-3 text-[13px] font-medium md:flex lg:gap-5 lg:text-[15px] xl:gap-7" aria-label="Основное меню">
                <a href="{{ route('home') }}" class="shrink-0 whitespace-nowrap text-white/90 transition-colors hover:text-moto-amber">Главная</a>
                @foreach($tenantMainMenuPages ?? [] as $navItem)
                    <a href="{{ $navItem['url'] }}" class="shrink-0 whitespace-nowrap text-white/80 transition-colors hover:text-white">{{ $navItem['label'] }}</a>
                @endforeach
            </nav>

            <div class="expert-header-bar__actions flex shrink-0 items-center justify-end gap-1.5 sm:gap-2">
                @if($contacts['phone'] ?? null)
                    @php $telDigits = preg_replace('/\D/', '', $contacts['phone']); @endphp
                    <a href="tel:{{ $telDigits }}"
                       class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-xl border border-white/18 bg-white/[0.04] text-white/90 transition-colors hover:border-moto-amber/40 hover:bg-white/[0.08] md:min-h-10 md:gap-2 md:px-3 md:py-2 lg:px-4"
                       aria-label="Позвонить: {{ $contacts['phone'] }}">
                        <svg class="h-5 w-5 shrink-0 text-moto-amber md:h-4 md:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <span class="hidden max-w-[11rem] truncate text-sm font-medium text-white/85 lg:inline xl:max-w-none xl:whitespace-nowrap">{{ $contacts['phone'] }}</span>
                    </a>
                @endif
                <button type="button"
                        class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-xl border border-white/20 text-white/90 transition-colors hover:border-white/40 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber md:hidden"
                        @click.stop="mobileNavOpen = !mobileNavOpen"
                        :aria-expanded="mobileNavOpen"
                        aria-controls="tenant-mobile-nav"
                        aria-label="Меню">
                    <svg x-show="!mobileNavOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileNavOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        @else
        <div class="mx-auto flex h-full w-full max-w-7xl items-center justify-between gap-2 px-3 sm:px-4 md:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="group relative flex min-w-0 max-w-[min(100%,70vw)] items-center gap-2 sm:max-w-[65vw] sm:gap-3 lg:max-w-none lg:gap-5" style="text-shadow: 0 2px 10px rgba(0,0,0,0.6);">
                <div class="pointer-events-none absolute inset-0 rounded-xl bg-white/[0.03] opacity-0 transition duration-300 group-hover:opacity-100"></div>
                @if(($branding['logo'] ?? null))
                    <img src="{{ $branding['logo'] }}" alt="{{ $site_name ?? config('app.name') }}"
                         width="96" height="96"
                         loading="eager"
                         decoding="async"
                         class="relative h-12 w-12 shrink-0 rounded-full object-contain drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)] sm:h-14 sm:w-14 lg:h-24 lg:w-24" />
                @else
                    <img src="{{ theme_platform_asset_url('marketing/logo-round-dark.png') }}" alt="{{ $site_name ?? config('app.name') }}"
                         width="96" height="96"
                         loading="eager"
                         decoding="async"
                         class="relative h-12 w-12 shrink-0 rounded-full object-contain drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)] sm:h-14 sm:w-14 lg:h-24 lg:w-24" />
                @endif
                <span class="truncate text-lg font-bold leading-tight tracking-tight text-white sm:text-xl lg:whitespace-nowrap lg:text-2xl xl:text-3xl">{{ $site_name ?? config('app.name') }}</span>
            </a>

            <nav class="hidden flex-1 items-center justify-center gap-6 px-4 text-[15px] font-medium md:flex lg:gap-10 xl:gap-12 xl:text-base" aria-label="Основное меню">
                <a href="#catalog" class="shrink-0 text-white/90 transition-colors hover:text-moto-amber">Автопарк</a>
                @foreach($tenantMainMenuPages ?? [] as $navItem)
                    <a href="{{ $navItem['url'] }}" class="shrink-0 text-white/80 transition-colors hover:text-white">{{ $navItem['label'] }}</a>
                @endforeach
            </nav>

            @if($contacts['phone'] ?? null)
            <a href="tel:{{ preg_replace('/\D/', '', $contacts['phone']) }}" class="hidden min-h-10 items-center gap-2 text-sm font-medium text-white/80 transition-colors hover:text-white lg:flex lg:min-w-[160px] lg:justify-end lg:pl-6">
                <svg class="h-4 w-4 shrink-0 text-moto-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                <span class="hidden xl:inline">{{ $contacts['phone'] }}</span>
            </a>
            @endif

            <button type="button"
                    class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl border border-white/20 text-white/90 transition-colors hover:border-white/40 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber md:hidden"
                    @click.stop="mobileNavOpen = !mobileNavOpen"
                    :aria-expanded="mobileNavOpen"
                    aria-controls="tenant-mobile-nav"
                    aria-label="Меню">
                <svg x-show="!mobileNavOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="mobileNavOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif

        <div id="tenant-mobile-nav"
             x-show="mobileNavOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="absolute left-0 right-0 top-full z-40 max-h-[min(70vh,calc(100dvh-4rem))] overflow-y-auto border-b border-white/10 bg-obsidian/95 px-3 py-3 shadow-lg backdrop-blur-md md:hidden"
             role="navigation"
             aria-label="Мобильное меню">
            <div class="flex flex-col gap-1">
                @if($isExpertAuto)
                    <a href="{{ route('home') }}" @click="mobileNavOpen = false" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium text-white/90 hover:bg-white/10">Главная</a>
                    @foreach($tenantMainMenuPages ?? [] as $navItem)
                        <a href="{{ $navItem['url'] }}" @click="mobileNavOpen = false" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium text-white/90 hover:bg-white/10">{{ $navItem['label'] }}</a>
                    @endforeach
                @else
                    <a href="#catalog" @click="mobileNavOpen = false" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium text-white/90 hover:bg-white/10">Автопарк</a>
                    @foreach($tenantMainMenuPages ?? [] as $navItem)
                        <a href="{{ $navItem['url'] }}" @click="mobileNavOpen = false" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium text-white/90 hover:bg-white/10">{{ $navItem['label'] }}</a>
                    @endforeach
                @endif
                @if($contacts['phone'] ?? null)
                <a href="tel:{{ preg_replace('/\D/', '', $contacts['phone']) }}" @click="mobileNavOpen = false" class="mt-1 flex min-h-11 items-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-base font-semibold text-moto-amber hover:bg-white/5">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    {{ $contacts['phone'] }}
                </a>
                @endif
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        if (window.tenantHeaderRegistered) return;
        window.tenantHeaderRegistered = true;
        Alpine.data('tenantHeader', () => ({
            mobileNavOpen: false,
            compact: false,
            videoPlaying: false,
            init() {
                const update = () => {
                    this.compact = (window.pageYOffset || document.documentElement.scrollTop) > 80;
                };
                update();
                window.addEventListener('scroll', update, { passive: true });
                this.$watch('mobileNavOpen', (open) => {
                    document.body.classList.toggle('overflow-hidden', open);
                });
                window.matchMedia('(min-width: 768px)').addEventListener('change', (e) => {
                    if (e.matches) {
                        this.mobileNavOpen = false;
                    }
                });
            },
        }));
    });
    </script>
</header>
