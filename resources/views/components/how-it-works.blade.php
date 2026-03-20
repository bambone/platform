@props(['section' => null])
<section class="py-20 lg:py-28 relative z-10 bg-obsidian overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 md:px-8">
        <div class="text-center mb-16 max-w-2xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Как это работает</h2>
            <p class="text-silver/80 text-lg">Весь процесс занимает не более 15 минут. Четыре шага — и вы в пути.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8 relative z-10">
            <!-- Hidden connecting line on desktop -->
            <div class="hidden lg:block absolute top-[28px] left-[10%] right-[10%] h-[1px] bg-gradient-to-r from-transparent via-white/10 to-transparent z-0"></div>

            <!-- Step 1 -->
            <div class="relative pt-6 group">
                <!-- Large Number Watermark -->
                <span class="absolute -top-10 -left-2 text-[120px] font-black text-moto-amber/5 leading-none z-0 selection:bg-transparent pointer-events-none group-hover:text-moto-amber/10 transition-colors duration-500">01</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-carbon border border-white/10 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Выберите байк</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Модель + даты. Всё.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="relative pt-6 group">
                <span class="absolute -top-10 -left-2 text-[120px] font-black text-moto-amber/5 leading-none z-0 selection:bg-transparent pointer-events-none group-hover:text-moto-amber/10 transition-colors duration-500">02</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-carbon border border-white/10 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Оставьте заявку</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Имя, телефон, даты — 2 минуты.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="relative pt-6 group">
                <span class="absolute -top-10 -left-2 text-[120px] font-black text-moto-amber/5 leading-none z-0 selection:bg-transparent pointer-events-none group-hover:text-moto-amber/10 transition-colors duration-500">03</span>
                
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-carbon border border-white/10 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Бронь подтверждена</h3>
                    <p class="text-silver text-sm leading-relaxed pr-4">Менеджер свяжется в течение 10 минут.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="relative pt-6 group">
                <span class="absolute -top-10 -left-2 text-[120px] font-black text-moto-amber/5 leading-none z-0 selection:bg-transparent pointer-events-none group-hover:text-moto-amber/10 transition-colors duration-500">04</span>
                
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
