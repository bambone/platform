@props(['section' => null])
<section class="relative z-10 overflow-hidden bg-obsidian py-16 sm:py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
        <div class="mx-auto mb-10 max-w-2xl text-center sm:mb-14">
            <h2 class="mb-3 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">Как это работает</h2>
            <p class="text-sm leading-relaxed text-silver/80 sm:text-base md:text-lg">Весь процесс занимает не более 15 минут. Четыре шага — и вы в пути.</p>
        </div>

        <div class="relative z-10 grid grid-cols-1 gap-10 sm:gap-12 md:grid-cols-2 md:gap-10 lg:grid-cols-4 lg:gap-8">
            <!-- Hidden connecting line on desktop -->
            <div class="hidden lg:block absolute top-[28px] left-[10%] right-[10%] h-[1px] bg-gradient-to-r from-transparent via-white/10 to-transparent z-0"></div>

            <!-- Step 1 -->
            <div class="group relative overflow-hidden pt-6">
                <!-- Large Number Watermark -->
                <span class="pointer-events-none absolute -left-1 -top-8 z-0 select-none text-7xl font-black leading-none text-moto-amber/5 transition-colors duration-500 sm:-top-10 sm:text-8xl lg:-left-2 lg:text-[120px] group-hover:text-moto-amber/10">01</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-carbon border border-white/10 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Выберите байк</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Модель + даты. Всё.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="group relative overflow-hidden pt-6">
                <span class="pointer-events-none absolute -left-1 -top-8 z-0 select-none text-7xl font-black leading-none text-moto-amber/5 transition-colors duration-500 sm:-top-10 sm:text-8xl lg:-left-2 lg:text-[120px] group-hover:text-moto-amber/10">02</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-carbon border border-white/10 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Оставьте заявку</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Имя, телефон, даты — 2 минуты.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="group relative overflow-hidden pt-6">
                <span class="pointer-events-none absolute -left-1 -top-8 z-0 select-none text-7xl font-black leading-none text-moto-amber/5 transition-colors duration-500 sm:-top-10 sm:text-8xl lg:-left-2 lg:text-[120px] group-hover:text-moto-amber/10">03</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-carbon border border-white/10 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Бронь подтверждена</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Менеджер свяжется в течение 10 минут.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="group relative overflow-hidden pt-6">
                <span class="pointer-events-none absolute -left-1 -top-8 z-0 select-none text-7xl font-black leading-none text-moto-amber/5 transition-colors duration-500 sm:-top-10 sm:text-8xl lg:-left-2 lg:text-[120px] group-hover:text-moto-amber/10">04</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-moto-amber border border-transparent rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-moto-amber/20">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Ключ на старт</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Чистый байк, полный бак — в путь.</p>
                </div>
            </div>

        </div>
    </div>
</section>
