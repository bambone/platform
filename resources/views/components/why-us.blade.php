@props(['section' => null])
<section class="py-20 lg:py-28 relative z-10 bg-[#0c0c0e] border-y border-white/[0.02]">
    <div class="max-w-7xl mx-auto px-4 md:px-8">
        <div class="mb-12 md:max-w-2xl">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-3">Почему выбирают нас</h2>
            <p class="text-silver/80 text-lg">Работаем с 2024 года. Никаких компромиссов в качестве и безопасности.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
            <!-- Block 1 -->
            <div class="bg-carbon rounded-2xl p-6 md:p-8 border border-white/5 hover:border-white/10 transition-colors flex flex-col sm:flex-row gap-6 items-start group">
                <div class="shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-moto-amber/20 to-orange-500/5 flex items-center justify-center text-moto-amber border border-moto-amber/20 shadow-inner group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">Вы получаете полностью обслуженный мотоцикл</h3>
                    <p class="text-silver text-sm sm:text-base leading-relaxed">Детейлинг и ТО перед каждой выдачей — без риска поломки в дороге. Вы едете спокойно.</p>
                </div>
            </div>

            <!-- Block 2 -->
            <div class="bg-carbon rounded-2xl p-6 md:p-8 border border-white/5 hover:border-white/10 transition-colors flex flex-col sm:flex-row gap-6 items-start group">
                <div class="shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-moto-amber/20 to-orange-500/5 flex items-center justify-center text-moto-amber border border-moto-amber/20 shadow-inner group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">Прозрачные условия — без сюрпризов</h3>
                    <p class="text-silver text-sm sm:text-base leading-relaxed">Цена в договоре = цена по факту. Полная страховка без скрытых доплат. КАСКО без франшизы — опция при бронировании.</p>
                </div>
            </div>

            <!-- Block 3 -->
            <div class="bg-carbon rounded-2xl p-6 md:p-8 border border-white/5 hover:border-white/10 transition-colors flex flex-col sm:flex-row gap-6 items-start group">
                <div class="shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-moto-amber/20 to-orange-500/5 flex items-center justify-center text-moto-amber border border-moto-amber/20 shadow-inner group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">Поддержка 24/7 — решим в пути</h3>
                    <p class="text-silver text-sm sm:text-base leading-relaxed">Попали в ситуацию? Мы на связи. Замена мотоцикла, консультация по маршруту — ответ в течение 15 минут.</p>
                </div>
            </div>

            <!-- Block 4 -->
            <div class="bg-carbon rounded-2xl p-6 md:p-8 border border-white/5 hover:border-white/10 transition-colors flex flex-col sm:flex-row gap-6 items-start group">
                <div class="shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-moto-amber/20 to-orange-500/5 flex items-center justify-center text-moto-amber border border-moto-amber/20 shadow-inner group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">Экипировка включена</h3>
                    <p class="text-silver text-sm sm:text-base leading-relaxed">Шлемы и базовая экипировка — чистая, продезинфицированная. Не везите с собой — получите при выдаче.</p>
                </div>
            </div>
        </div>
    </div>
</section>
