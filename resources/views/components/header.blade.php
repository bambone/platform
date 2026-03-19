<header x-data="headerScroll()"
        x-init="init()"
        @hero-video-playing.window="videoPlaying = true"
        @hero-video-stopped.window="videoPlaying = false"
        :class="(videoPlaying || compact) ? 'bg-obsidian/85 backdrop-blur-md shadow-sm' : 'bg-gradient-to-b from-black/60 to-transparent'"
        class="fixed top-0 w-full z-50 h-16 md:h-20 flex items-center transition-colors duration-300">
    <div class="w-full max-w-7xl mx-auto px-6 flex justify-between items-center h-full">
        <a href="{{ route('home') }}" class="flex items-center gap-3 shrink-0">
            <img src="{{ asset('images/logo-round-dark.png') }}" alt="Moto Levins"
                 width="96" height="96"
                 loading="eager"
                 class="w-12 h-12 md:w-14 md:h-14 lg:w-16 lg:h-16 object-contain rounded-full shrink-0" />
            <span class="text-lg md:text-xl lg:text-2xl font-bold tracking-tight text-white leading-none whitespace-nowrap">Moto Levins</span>
        </a>

        <nav class="hidden md:flex flex-1 items-center justify-center gap-10 xl:gap-12 px-8">
            <a href="#catalog" class="text-[15px] lg:text-base font-medium text-white/90 hover:text-moto-amber transition-colors whitespace-nowrap shrink-0">Автопарк</a>
            <a href="#" class="text-[15px] lg:text-base font-medium text-white/80 hover:text-white transition-colors whitespace-nowrap shrink-0">Правила аренды</a>
            <a href="#" class="text-[15px] lg:text-base font-medium text-white/80 hover:text-white transition-colors whitespace-nowrap shrink-0">Контакты</a>
        </nav>

        <a href="tel:+79130608689" class="hidden lg:flex items-center gap-2 text-sm font-medium text-white/80 hover:text-white transition-colors shrink-0 pl-6 min-w-[160px] justify-end">
            <svg class="w-4 h-4 text-moto-amber shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
            <span class="hidden xl:inline">+7 (913) 060-86-89</span>
        </a>
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
