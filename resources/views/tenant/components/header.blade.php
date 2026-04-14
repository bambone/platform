@php
    $isExpertAuto = tenant()?->themeKey() === 'expert_auto';
    $isAdvocateEditorial = tenant()?->themeKey() === 'advocate_editorial';
    $isExpertStyleNav = $isExpertAuto || $isAdvocateEditorial;
    $headerBrandTitle = $site_name ?? config('app.name');
    if ($isExpertAuto && is_string($headerBrandTitle)) {
        $parts = preg_split('/\s*[—–]\s*/u', $headerBrandTitle, 2);
        $headerBrandTitle = trim((string) ($parts[0] ?? $headerBrandTitle));
        if ($headerBrandTitle === '') {
            $headerBrandTitle = 'Марат Афлятунов';
        }
    }
    /** Scroll / overlay surfaces: opacity-only crossfade (see .tenant-header in app.css). */
    $headerSurfaceRest = $isAdvocateEditorial
        ? 'bg-gradient-to-b from-[#fdfcfa]/92 via-[#fdfcfa]/55 to-transparent'
        : 'bg-gradient-to-b from-[#050608]/90 via-[#050608]/40 to-transparent';
    $headerSurfaceSolid = $isAdvocateEditorial
        ? 'bg-[#f7f4ef]/95 shadow-[0_8px_30px_rgba(28,31,38,0.08)]'
        : 'bg-[#050608] shadow-[0_4px_32px_rgba(0,0,0,0.4)]';
    $headerDividerBg = $isAdvocateEditorial ? 'bg-stone-200/90' : 'bg-white/[0.06]';
@endphp
{{-- fixed: full-bleed hero under bar; flicker fix = opacity-only surfaces + 1px divider layer (no border-width toggle). --}}
<header x-data="tenantHeader()"
        x-init="init()"
        @keydown.escape.window="mobileNavOpen = false"
        @hero-video-playing.window="videoPlaying = true"
        @hero-video-stopped.window="videoPlaying = false"
        class="tenant-header fixed top-0 left-0 right-0 z-50 flex flex-col {{ $isExpertStyleNav ? 'h-[3.75rem]' : 'h-[4.5rem]' }} md:h-[5rem] lg:h-[5.5rem]">
    <div class="relative flex h-full w-full shrink-0 items-center">
        {{-- Same backdrop-blur on both fills: avoids Safari/Retina hairline when blur toggles with opacity crossfade. --}}
        <div class="tenant-header__fill pointer-events-none absolute inset-0 z-0 transition-opacity duration-300 ease-out will-change-[opacity] backdrop-blur-xl {{ $headerSurfaceRest }}"
             aria-hidden="true"
             :class="surfaceScrolled() ? 'opacity-0' : 'opacity-100'"></div>
        <div class="tenant-header__fill pointer-events-none absolute inset-0 z-0 transition-opacity duration-300 ease-out will-change-[opacity] backdrop-blur-xl {{ $headerSurfaceSolid }}"
             aria-hidden="true"
             :class="surfaceScrolled() ? 'opacity-100' : 'opacity-0'"></div>
        <div class="tenant-header__divider pointer-events-none absolute bottom-0 left-0 right-0 z-[1] h-px transition-opacity duration-300 ease-out will-change-[opacity] {{ $headerDividerBg }}"
             aria-hidden="true"
             :class="surfaceScrolled() ? 'opacity-100' : 'opacity-0'"></div>

        <div class="relative z-10 flex h-full w-full min-w-0 items-center">
        @if($isExpertStyleNav)
        {{-- Три зоны: бренд | навигация | телефон; увеличенный отступ, убран конфликт --}}
        <div class="expert-header-bar mx-auto flex h-full w-full max-w-[100rem] items-center justify-between gap-3 px-4 md:gap-4 md:px-8 lg:px-12">
            <a href="{{ route('home') }}" class="expert-header-bar__brand group relative flex min-w-0 max-w-[65vw] items-center gap-2.5 md:gap-3">
                @if(($branding['logo'] ?? null))
                    <img src="{{ $branding['logo'] }}" alt="{{ $headerBrandTitle }}"
                         width="96" height="96"
                         loading="eager"
                         decoding="async"
                         class="relative shrink-0 object-contain {{ $isAdvocateEditorial ? 'h-11 w-11 rounded-xl bg-[#faf8f5]/95 p-[3px] shadow-[0_1px_4px_rgba(28,31,38,0.09)] ring-1 ring-stone-300/90 md:h-12 md:w-12' : 'h-9 w-9 md:h-11 md:w-11' }}" />
                @else
                    @include('tenant.components.expert-brand-mark', ['compact' => true])
                @endif
                <span class="min-w-0 truncate text-[15px] font-bold tracking-wide md:text-[17px] lg:text-[19px] {{ $isAdvocateEditorial ? 'text-stone-900' : 'text-white/95' }}">{{ $headerBrandTitle }}</span>
            </a>

            <nav class="expert-header-bar__nav hidden flex-1 items-center justify-center gap-6 text-[14px] font-semibold tracking-wide md:flex lg:gap-10 lg:text-[15px]" aria-label="Основное меню">
                <a href="{{ route('home') }}" class="shrink-0 transition-colors hover:text-moto-amber {{ $isAdvocateEditorial ? 'text-stone-800' : 'text-white/95' }}">Главная</a>
                @foreach($tenantMainMenuPages ?? [] as $navItem)
                    <a href="{{ $navItem['url'] }}" class="shrink-0 transition-colors {{ $isAdvocateEditorial ? 'text-stone-600 hover:text-stone-900' : 'text-silver/80 hover:text-white' }}">{{ $navItem['label'] }}</a>
                @endforeach
            </nav>

            <div class="expert-header-bar__actions flex shrink-0 items-center gap-4">
                @if($contacts['phone'] ?? null)
                    @php $telDigits = preg_replace('/\D/', '', $contacts['phone']); @endphp
                    <a href="tel:{{ $telDigits }}"
                       class="hidden text-[15px] font-semibold tracking-wide transition-colors hover:text-moto-amber md:block {{ $isAdvocateEditorial ? 'text-stone-800' : 'text-white/90' }}"
                       aria-label="Позвонить: {{ $contacts['phone'] }}">
                        {{ $contacts['phone'] }}
                    </a>
                @endif
                <button type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl transition-colors md:hidden {{ $isAdvocateEditorial ? 'text-stone-800 hover:bg-stone-900/[0.06]' : 'text-white/90 hover:bg-white/[0.05]' }}"
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
                <div class="pointer-events-none absolute inset-0 rounded-xl bg-white/[0.03] opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
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
             x-transition:enter="transition-opacity ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="tenant-mobile-nav-panel absolute inset-x-0 top-full z-[45] -mt-px overflow-y-auto border-b px-3 py-3 shadow-lg md:hidden {{ $isAdvocateEditorial ? 'border-stone-200 bg-[#fbf9f6]' : 'border-white/[0.08] bg-[#080b10]' }} {{ $isExpertStyleNav ? 'max-h-[min(72vh,calc(100dvh-3.75rem))]' : 'max-h-[min(72vh,calc(100dvh-4.5rem))]' }}"
             role="navigation"
             aria-label="Мобильное меню">
            <div class="flex flex-col gap-1">
                @if($isExpertStyleNav)
                    <a href="{{ route('home') }}" @click="mobileNavOpen = false" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium {{ $isAdvocateEditorial ? 'text-stone-800 hover:bg-stone-900/[0.06]' : 'text-white/90 hover:bg-white/10' }}">Главная</a>
                    @foreach($tenantMainMenuPages ?? [] as $navItem)
                        <a href="{{ $navItem['url'] }}" @click="mobileNavOpen = false" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium {{ $isAdvocateEditorial ? 'text-stone-800 hover:bg-stone-900/[0.06]' : 'text-white/90 hover:bg-white/10' }}">{{ $navItem['label'] }}</a>
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
                @if($isExpertStyleNav)
                    <a href="{{ route('home') }}#expert-inquiry" @click="mobileNavOpen = false" class="mt-3 flex min-h-12 items-center justify-center rounded-xl bg-moto-amber px-4 text-[15px] font-bold text-black shadow-lg shadow-moto-amber/15">
                        {{ $isAdvocateEditorial ? 'Связаться' : 'Записаться' }}
                    </a>
                @endif
            </div>
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
            surfaceScrolled() {
                return this.compact || this.videoPlaying || this.mobileNavOpen;
            },
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
