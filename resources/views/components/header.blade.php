<header x-data="headerScroll()"
        x-init="init()"
        @hero-video-playing.window="videoPlaying = true"
        @hero-video-stopped.window="videoPlaying = false"
        :class="(videoPlaying || compact) ? 'bg-obsidian/85 backdrop-blur-md shadow-sm' : 'bg-gradient-to-b from-black/60 to-transparent'"
        class="fixed top-0 w-full z-50 h-16 md:h-20 flex items-center transition-colors duration-300">
    <div class="w-full max-w-7xl mx-auto px-6 flex justify-between items-center h-full">
        <a href="{{ route('home') }}" class="group relative flex items-center gap-3 lg:gap-5 shrink-0" style="text-shadow: 0 2px 10px rgba(0,0,0,0.6);">
            <div class="absolute inset-0 rounded-xl bg-white/[0.03] opacity-0 group-hover:opacity-100 transition duration-300 pointer-events-none"></div>
            @if(($branding['logo'] ?? null))
                <img src="{{ $branding['logo'] }}" alt="{{ $site_name ?? config('app.name') }}"
                     width="96" height="96"
                     loading="eager"
                     class="relative w-16 h-16 lg:w-24 lg:h-24 object-contain rounded-full shrink-0 drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)]" />
            @else
                <img src="{{ asset('images/logo-round-dark.png') }}" alt="{{ $site_name ?? config('app.name') }}"
                     width="96" height="96"
                     loading="eager"
                     class="relative w-16 h-16 lg:w-24 lg:h-24 object-contain rounded-full shrink-0 drop-shadow-[0_4px_12px_rgba(0,0,0,0.5)]" />
            @endif
            <span class="relative text-2xl lg:text-3xl font-bold tracking-tight text-white leading-none whitespace-nowrap">{{ $site_name ?? config('app.name') }}</span>
        </a>

        <nav class="hidden md:flex flex-1 items-center justify-center gap-10 xl:gap-12 px-8">
            <a href="#catalog" class="text-[15px] lg:text-base font-medium text-white/90 hover:text-moto-amber transition-colors whitespace-nowrap shrink-0">Автопарк</a>
            <a href="{{ route('terms') }}" class="text-[15px] lg:text-base font-medium text-white/80 hover:text-white transition-colors whitespace-nowrap shrink-0">Правила аренды</a>
            <a href="{{ route('contacts') }}" class="text-[15px] lg:text-base font-medium text-white/80 hover:text-white transition-colors whitespace-nowrap shrink-0">Контакты</a>
        </nav>

        @if($contacts['phone'] ?? null)
        <a href="tel:{{ preg_replace('/\D/', '', $contacts['phone']) }}" class="hidden lg:flex items-center gap-2 text-sm font-medium text-white/80 hover:text-white transition-colors shrink-0 pl-6 min-w-[160px] justify-end">
            <svg class="w-4 h-4 text-moto-amber shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
            <span class="hidden xl:inline">{{ $contacts['phone'] }}</span>
        </a>
        @endif
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        if (window.headerScrollRegistered) return;
        window.headerScrollRegistered = true;
        Alpine.data('headerScroll', () => ({
            compact: false,
            videoPlaying: false,
            init() {
                const update = () => { this.compact = (window.pageYOffset || document.documentElement.scrollTop) > 80; };
                update();
                window.addEventListener('scroll', update, { passive: true });
            }
        }));
    });
    </script>
</header>
