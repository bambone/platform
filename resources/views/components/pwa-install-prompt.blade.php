<div x-data="pwaInstallPrompt()"
     x-init="initPrompt()"
     x-show="showPrompt"
     x-transition:enter="transition ease-out duration-500"
     x-transition:enter-start="opacity-0 translate-y-full"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-full"
     class="fixed bottom-4 inset-x-4 md:bottom-8 md:right-8 md:left-auto md:w-96 z-[60] bg-carbon/90 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl p-5"
     style="display: none;">
    
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-moto-amber to-orange-700 flex items-center justify-center shrink-0 shadow-lg">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
        </div>
        <div class="flex-1">
            <h4 class="text-white font-bold text-base mb-1">Установить Moto Levins</h4>
            <p class="text-silver/90 text-sm leading-snug mb-3">Добавьте на главный экран для быстрого доступа к каталогу в 1 клик.</p>
            
            <!-- iOS Instructions -->
            <div x-show="isIos" class="bg-white/5 rounded-lg p-3 text-sm text-silver mb-4 border border-white/5" style="display: none;">
                Нажмите значок <svg class="inline w-4 h-4 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> <b>Поделиться</b><br>
                и выберите <b>«На экран Домой»</b>
            </div>

            <div class="flex gap-3">
                <button x-show="!isIos" @click="installApp()" class="flex-1 bg-moto-amber hover:bg-orange-600 text-white font-bold text-sm py-2 px-4 rounded-lg transition-colors shadow-lg shadow-moto-amber/20">Установить</button>
                <button @click="dismissPrompt()" class="flex-1 px-4 py-2 bg-white/5 hover:bg-white/10 text-white text-sm font-medium rounded-lg transition-colors border border-white/10">Позже</button>
            </div>
        </div>
    </div>
</div>

<script>
// Capture the event globally as early as possible
window.pwaDeferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.pwaDeferredPrompt = e;
});

document.addEventListener('alpine:init', () => {
    Alpine.data('pwaInstallPrompt', () => ({
        deferredPrompt: null,
        showPrompt: false,
        isIos: false,
        
        initPrompt() {
            this.deferredPrompt = window.pwaDeferredPrompt;
            // Prevent showing if already installed/standalone
            if (window.matchMedia('(display-mode: standalone)').matches) return;

            // Check suppression policy (7 days max age)
            const dismissedAt = localStorage.getItem('pwa_prompt_dismissed');
            if (dismissedAt) {
                const daysPassed = (Date.now() - parseInt(dismissedAt)) / (1000 * 60 * 60 * 24);
                if (daysPassed < 7) return; 
            }

            // Detect iOS strictly
            const ua = window.navigator.userAgent;
            this.isIos = !!ua.match(/iPad/i) || !!ua.match(/iPhone/i) || (ua.match(/Mac/) && navigator.maxTouchPoints > 1);


            
            // Contextual trigger 1: Time
            setTimeout(() => {
                if (this.deferredPrompt || this.isIos) this.showPrompt = true;
            }, 10000); // 10s for production confidence

            // Contextual trigger 2: Meaningful scroll
            const scrollHandler = () => {
                if (window.scrollY > window.innerHeight * 0.5) {
                    if (this.deferredPrompt || this.isIos) this.showPrompt = true;
                    window.removeEventListener('scroll', scrollHandler);
                }
            };
            window.addEventListener('scroll', scrollHandler, { passive: true });
            
            window.addEventListener('appinstalled', () => {
                this.showPrompt = false;
                this.deferredPrompt = null;
                console.log('Moto Levins PWA safely installed');
            });
        },

        async installApp() {
            if (!this.deferredPrompt) return;
            
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('PWA installation accepted');
            } else {
                this.dismissPrompt();
            }
            
            this.deferredPrompt = null;
            this.showPrompt = false;
        },

        dismissPrompt() {
            this.showPrompt = false;
            localStorage.setItem('pwa_prompt_dismissed', Date.now().toString());
        }
    }));
});
</script>
