<section id="control-center" class="pm-section-anchor relative overflow-hidden bg-navy py-16 sm:py-24" aria-labelledby="control-heading">
    <!-- Ambient glowing core behind everything -->
    <div class="pointer-events-none absolute left-1/2 top-1/2 h-[800px] w-[800px] -translate-x-1/2 -translate-y-1/2 animate-glow-breath rounded-full bg-pm-accent/20 blur-[120px]"></div>

    <div class="relative z-10 mx-auto flex max-w-6xl flex-col items-center gap-12 px-3 sm:px-4 md:px-6 lg:flex-row lg:gap-8">
        <!-- Text Content -->
        <div class="w-full lg:w-1/3">
            <h2 id="control-heading" class="fade-reveal text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">Одно окно вместо пяти закладок</h2>
            <p class="fade-reveal mt-4 font-semibold leading-relaxed text-white sm:text-lg" style="transition-delay: 80ms;">Меньше «а куда делась эта заявка?» — записи и люди живут там, где их ждать.</p>
            <p class="fade-reveal mt-3 text-slate-300 leading-relaxed" style="transition-delay: 100ms;">Мы собрали на одном входе то, без чего день салона или проката разваливается: расписание, база клиентов, настройки услуг. Не надо синхронизировать руками между полудюжиной аккаунтов.</p>
            <ul class="fade-reveal mt-8 space-y-4" style="transition-delay: 200ms;">
                <li class="flex items-center gap-3 text-sm text-slate-300">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-pm-accent/20">
                        <div class="h-1.5 w-1.5 rounded-full bg-pm-accent"></div>
                    </div>
                    Управление бронированиями
                </li>
                <li class="flex items-center gap-3 text-sm text-slate-300">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-pm-accent/20">
                        <div class="h-1.5 w-1.5 rounded-full bg-pm-accent"></div>
                    </div>
                    Клиентская база и&nbsp;CRM
                </li>
                <li class="flex items-center gap-3 text-sm text-slate-300">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-pm-accent/20">
                        <div class="h-1.5 w-1.5 rounded-full bg-pm-accent"></div>
                    </div>
                    Настройки парка и&nbsp;услуг
                </li>
            </ul>
            <p class="fade-reveal mt-8 text-sm font-medium text-slate-200" style="transition-delay: 250ms;">Рабочее место вашей команды — одно, а не три разных «кабинета».</p>
        </div>

        <!-- Node Infrastructure UI -->
        <div class="fade-reveal relative flex h-[500px] w-full items-center justify-center lg:w-2/3" style="transition-delay: 300ms;">
            <!-- Connection Lines -->
            <svg class="pointer-events-none absolute inset-0 h-full w-full animate-pulse-slow text-slate-700/50" style="animation-duration: 4s;" xmlns="http://www.w3.org/2000/svg">
                <line x1="50%" y1="50%" x2="20%" y2="20%" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
                <line x1="50%" y1="50%" x2="80%" y2="25%" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
                <line x1="50%" y1="50%" x2="25%" y2="80%" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
                <line x1="50%" y1="50%" x2="75%" y2="80%" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" />
            </svg>

            <!-- Core Node -->
            <div class="animate-pulse-slow relative z-20 flex h-32 w-32 flex-col items-center justify-center rounded-3xl border border-slate-700 bg-slate-900 shadow-[0_0_50px_rgba(79,70,229,0.3)]">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-pm-accent shadow-[0_0_15px_rgba(79,70,229,0.5)]">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div class="mt-2 text-[10px] font-bold uppercase tracking-wider text-white">Рабочее место</div>
            </div>

            <!-- Satellites -->
            <div class="glass-panel-dark absolute left-[10%] top-10 flex h-12 w-24 cursor-pointer items-center justify-center text-xs font-medium text-white shadow-xl transition-transform hover:-translate-y-1 hover:border-pm-accent">Заявки</div>
            <div class="glass-panel-dark absolute right-[10%] top-[15%] flex h-12 w-24 cursor-pointer items-center justify-center text-xs font-medium text-white shadow-xl transition-transform hover:-translate-y-1 hover:border-pm-accent">Клиенты</div>
            <div class="glass-panel-dark absolute bottom-[10%] left-[15%] flex h-12 w-24 cursor-pointer items-center justify-center text-xs font-medium text-white shadow-xl transition-transform hover:-translate-y-1 hover:border-pm-accent">Менеджмент</div>
            <div class="glass-panel-dark absolute bottom-[15%] right-[15%] flex h-12 w-24 cursor-pointer items-center justify-center text-xs font-medium text-white shadow-xl transition-transform hover:-translate-y-1 hover:border-pm-accent">Загрузка</div>
        </div>
    </div>
</section>
