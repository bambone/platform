@props(['section' => null])
@php
    $videoPoster = $section['video_poster'] ?? 'images/hero-bg.png';
    $videoPoster = str_starts_with($videoPoster, 'http') ? $videoPoster : asset($videoPoster);
    $videoSrc = $section['video_src'] ?? 'videos/Moto_levins_1.mp4';
    $videoSrc = str_starts_with($videoSrc, 'http') ? $videoSrc : asset($videoSrc);
    $heading = $section['heading'] ?? 'Аренда мотоциклов на Чёрном море';
    $subheading = $section['subheading'] ?? 'от 4 000 ₽/сутки';
    $description = $section['description'] ?? 'Геленджик · Анапа · Новороссийск — без скрытых платежей, экипировка и страховка включены';
@endphp
<section x-data="heroVideo()"
         x-init="init()"
         @scroll.window="onScroll()"
         @wheel.window="onWheel()"
         @touchmove.window="onTouchMove()"
         @keydown.escape.window="onEsc()"
         id="hero-section"
         class="relative w-full min-h-screen flex items-center justify-center overflow-hidden bg-obsidian pt-24 md:pt-28 pb-16 md:pb-20">

    <div x-show="videoPlaying"
         x-cloak
         x-transition:enter="transition-opacity ease-out duration-600"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-400"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 z-20">
        <div class="absolute inset-0 bg-black/10 pointer-events-none z-[22]"></div>
        <video x-ref="heroVideo"
               class="absolute inset-0 w-full h-full object-cover"
               playsinline preload="metadata"
               poster="{{ $videoPoster }}"
               @ended="onVideoEnded"
               aria-label="POV-поездка на мотоцикле по южным дорогам">
            <source src="{{ $videoSrc }}" type="video/mp4">
        </video>
        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/50 pointer-events-none z-[25]"></div>
        <div class="absolute bottom-6 sm:bottom-8 left-1/2 -translate-x-1/2 flex items-center gap-3 px-5 py-3 rounded-2xl bg-black/70 backdrop-blur-xl border border-white/15 z-[100] pb-[max(1rem,env(safe-area-inset-bottom))]">
            <button @click="togglePlay" :aria-label="isPaused ? 'Воспроизвести' : 'Пауза'" class="p-2.5 text-white/80 hover:text-white rounded-xl hover:bg-white/10 transition-all duration-200">
                <svg x-show="isPaused" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <svg x-show="!isPaused" x-cloak class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>
            <button @click="toggleMute" :aria-label="videoMuted ? 'Включить звук' : 'Выключить звук'" class="p-2.5 text-white/80 hover:text-white rounded-xl hover:bg-white/10 transition-all duration-200">
                <svg x-show="videoMuted" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                <svg x-show="!videoMuted" x-cloak class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
            </button>
            <input type="range" min="0" max="1" step="0.01" x-model="volume" @input="setVolume($event)" class="w-20 accent-moto-amber h-1.5 rounded-full cursor-pointer" aria-label="Громкость">
            <button @click="closeVideo" aria-label="Закрыть видео" class="p-2.5 text-white/80 hover:text-white rounded-xl hover:bg-white/10 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div x-show="!videoPlaying" class="absolute inset-0 z-0">
        <img src="{{ asset('images/hero-bg.png') }}" alt="Motorcycle background" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
        <div class="w-full h-full bg-gradient-to-br from-carbon to-obsidian hidden"></div>
        <div class="absolute inset-0 bg-black/15 pointer-events-none"></div>
        <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(ellipse 70% 55% at center, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0) 70%);"></div>
    </div>

    <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 md:px-8 flex flex-col items-center text-center">
        <div class="max-w-6xl mx-auto w-full" :class="videoPlaying && 'opacity-0 pointer-events-none'">
            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-[4.25rem] xl:text-[5rem] leading-[1.08] font-extrabold tracking-tight text-white mb-6"
                style="text-shadow: 0 4px 16px rgba(0,0,0,0.8);">
                {!! nl2br(e($heading)) !!}
                <span class="block mt-1 md:mt-2 text-transparent bg-clip-text bg-gradient-to-r from-moto-amber via-orange-400 to-orange-500">{{ $subheading }}</span>
            </h1>
        </div>

        <div class="max-w-2xl mx-auto w-full mb-8" :class="videoPlaying && 'opacity-0 pointer-events-none'">
            <p class="text-base md:text-lg text-white/90 font-medium leading-relaxed max-w-2xl mx-auto"
               style="text-shadow: 0 2px 8px rgba(0,0,0,0.6);">
                {{ $description }}
            </p>
        </div>

        <div class="w-full max-w-5xl" :class="videoPlaying && 'opacity-0 pointer-events-none'">
            <x-booking-bar />
        </div>

        <div class="mt-4" :class="videoPlaying && 'opacity-0 pointer-events-none'">
            <x-trust-chips />
        </div>

        <div class="mt-6 z-20 relative" :class="videoPlaying && 'opacity-0 pointer-events-none'">
            <button @click="playVideo" type="button"
                    class="inline-flex items-center gap-3 px-5 py-2.5 border border-white/15 text-white/65 hover:text-white/80 hover:border-white/30 rounded-xl transition-colors"
                    :aria-label="videoEnded ? 'Посмотреть видео ещё раз' : 'Смотреть видео'">
                <svg class="w-5 h-5 text-moto-amber" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <span x-text="videoEnded ? 'Посмотреть ещё раз' : 'Смотреть, как это ощущается'" class="text-sm"></span>
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        if (window.heroVideoRegistered) return;
        window.heroVideoRegistered = true;

        Alpine.data('heroVideo', () => ({
            videoPlaying: false,
            videoMuted: false,
            volume: 0.12,
            videoEnded: false,
            isPaused: false,
            reducedMotion: false,
            heroVisibleRatio: 1,

            init() {
                this.volume = 0.12;
                this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden && this.videoPlaying) this.pauseVideo();
                });
                const hero = document.getElementById('hero-section');
                if (!hero) return;
                const io = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        this.heroVisibleRatio = entry.intersectionRatio;
                        if (!this.videoPlaying) return;
                        if (entry.intersectionRatio < 0.4) {
                            this.closeVideo();
                            this.resetToPoster();
                        } else if (entry.intersectionRatio < 0.6) {
                            this.pauseVideo();
                        }
                    });
                }, { threshold: [0, 0.2, 0.4, 0.6, 0.8, 1] });
                io.observe(hero);
            },

            playVideo() {
                const v = this.$refs.heroVideo;
                if (!v) return;
                this.videoEnded = false;
                this.videoPlaying = true;
                this.isPaused = false;
                window.dispatchEvent(new CustomEvent('hero-video-playing'));
                this.$nextTick(() => {
                    v.muted = this.videoMuted;
                    v.volume = this.videoMuted ? 0 : this.volume;
                    v.currentTime = 0;
                    v.play().catch(() => {});
                });
            },

            closeVideo() {
                this.pauseVideo();
                this.videoPlaying = false;
                window.dispatchEvent(new CustomEvent('hero-video-stopped'));
            },

            pauseVideo() {
                const v = this.$refs.heroVideo;
                if (v && !v.paused) v.pause();
                this.isPaused = true;
            },

            togglePlay() {
                const v = this.$refs.heroVideo;
                if (!v) return;
                if (v.paused) { v.play(); this.isPaused = false; }
                else { v.pause(); this.isPaused = true; }
            },

            setVolume(e) {
                const video = this.$refs.heroVideo;
                this.volume = parseFloat(e.target.value);
                if (video) {
                    video.volume = this.volume;
                    this.videoMuted = this.volume === 0;
                    video.muted = this.videoMuted;
                }
            },

            toggleMute() {
                const video = this.$refs.heroVideo;
                if (!video) return;
                if (this.videoMuted) {
                    this.videoMuted = false;
                    this.volume = 0.12;
                    video.volume = this.volume;
                    video.muted = false;
                } else {
                    this.videoMuted = true;
                    video.volume = 0;
                    video.muted = true;
                }
            },

            onVideoEnded() {
                this.videoPlaying = false;
                this.videoEnded = true;
                this.isPaused = true;
                window.dispatchEvent(new CustomEvent('hero-video-stopped'));
            },

            resetToPoster() {
                const v = this.$refs.heroVideo;
                if (v) v.currentTime = 0;
            },

            onEsc()       { if (this.videoPlaying) this.closeVideo(); },
            onScroll()    { if (this.videoPlaying) this.pauseVideo(); },
            onWheel()     { if (this.videoPlaying) this.pauseVideo(); },
            onTouchMove() { if (this.videoPlaying) this.pauseVideo(); },
        }));
    });
    </script>
</section>
