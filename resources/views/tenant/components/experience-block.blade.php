@props(['section' => null])
<section class="relative z-10 bg-obsidian py-16 sm:py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
        <div class="mb-10 flex flex-col gap-4 sm:mb-12 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl min-w-0">
                <h2 class="mb-3 text-balance text-2xl font-bold leading-tight text-white drop-shadow-sm sm:text-3xl md:text-4xl">Выберите свой маршрут и стиль поездки</h2>
                <p class="text-sm leading-relaxed text-zinc-300 sm:text-base md:text-lg">Не просто мотоцикл — эмоция и результат. Каждая локация дарит свой кайф.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:gap-6 md:grid-cols-3 md:gap-6 lg:gap-8">
            <!-- Card 1 -->
            <div role="button" tabindex="0"
                 @click="filters.location = 'Геленджик'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 @keydown.enter.prevent="filters.location = 'Геленджик'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 @keydown.space.prevent="filters.location = 'Геленджик'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 class="group relative flex aspect-[4/5] cursor-pointer flex-col justify-end overflow-hidden rounded-2xl border border-white/5 bg-carbon p-5 shadow-xl transition-all duration-300 hover:border-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber md:aspect-[3/4] md:p-8 touch-manipulation">
                <div class="absolute inset-0 z-0">
                    <img src="{{ theme_platform_asset_url('marketing/experience-coastal.png') }}" alt="Побережье" width="800" height="1000" sizes="(max-width:768px) 100vw, (max-width:1280px) 50vw, 33vw" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-[15s] ease-out group-hover:scale-105" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                    <div class="w-full h-full bg-[#111113] hidden img-fallback relative overflow-hidden">
                        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-moto-amber/5 blur-[80px]"></div>
                    </div>
                </div>
                <div class="absolute inset-0 bg-black/10 group-hover:bg-transparent transition-colors duration-500 z-10"></div>
                <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-obsidian via-obsidian/80 to-transparent z-10"></div>
                
                <div class="relative z-20">
                    <span class="text-moto-amber text-[10px] sm:text-xs font-bold uppercase tracking-widest mb-3 block opacity-90">Серпантины и морской бриз</span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2 group-hover:text-moto-amber transition-colors drop-shadow-md leading-tight">Побережье<br>на закате</h3>
                    <p class="text-zinc-300 text-[13px] sm:text-sm mb-5 line-clamp-2 md:line-clamp-3 leading-relaxed">Закат, море и пустая дорога — вы получите те самые кадры и ощущения, ради которых едут на юг.</p>
                    <div class="mt-4 inline-flex items-center gap-2 px-6 py-2.5 bg-white/10 group-hover:bg-moto-amber text-white group-hover:text-[#0c0c0c] font-bold text-sm rounded-xl opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:translate-y-4 sm:group-hover:translate-y-0 transition-all duration-300 backdrop-blur-md border border-white/20 group-hover:border-moto-amber shadow-lg group-hover:shadow-moto-amber/20">
                        Выбрать маршрут <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                </div>
            </div>

            <!-- Card 2 -->
            <div role="button" tabindex="0"
                 @click="filters.location = 'Анапа'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 @keydown.enter.prevent="filters.location = 'Анапа'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 @keydown.space.prevent="filters.location = 'Анапа'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 class="group relative flex aspect-[4/5] cursor-pointer flex-col justify-end overflow-hidden rounded-2xl border border-white/5 bg-carbon p-5 shadow-xl transition-all duration-300 hover:border-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber md:aspect-[3/4] md:p-8 touch-manipulation">
                <div class="absolute inset-0 z-0">
                    <img src="{{ theme_platform_asset_url('marketing/experience-city.png') }}" alt="Город" width="800" height="1000" sizes="(max-width:768px) 100vw, (max-width:1280px) 50vw, 33vw" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-[15s] ease-out group-hover:scale-105" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                    <div class="w-full h-full bg-[#111113] hidden img-fallback relative overflow-hidden">
                        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-moto-amber/5 blur-[80px]"></div>
                    </div>
                </div>
                <div class="absolute inset-0 bg-black/10 group-hover:bg-transparent transition-colors duration-500 z-10"></div>
                <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-obsidian via-obsidian/80 to-transparent z-10"></div>
                
                <div class="relative z-20">
                    <span class="text-moto-amber text-[10px] sm:text-xs font-bold uppercase tracking-widest mb-3 block opacity-90">Динамика и стиль</span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2 group-hover:text-moto-amber transition-colors drop-shadow-md leading-tight">Городские<br>артерии</h3>
                    <p class="text-zinc-300 text-[13px] sm:text-sm mb-5 line-clamp-2 md:line-clamp-3 leading-relaxed">Чувствуйте город на скорости — лёгкий байк, манёвренность, адреналин без лишних километров.</p>
                    <div class="mt-4 inline-flex items-center gap-2 px-6 py-2.5 bg-white/10 group-hover:bg-moto-amber text-white group-hover:text-[#0c0c0c] font-bold text-sm rounded-xl opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:translate-y-4 sm:group-hover:translate-y-0 transition-all duration-300 backdrop-blur-md border border-white/20 group-hover:border-moto-amber shadow-lg group-hover:shadow-moto-amber/20">
                        Выбрать маршрут <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                </div>
            </div>

            <!-- Card 3 -->
            <div role="button" tabindex="0"
                 @click="filters.location = 'Новороссийск'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 @keydown.enter.prevent="filters.location = 'Новороссийск'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 @keydown.space.prevent="filters.location = 'Новороссийск'; document.getElementById('catalog').scrollIntoView({behavior: 'smooth'})"
                 class="group relative flex aspect-[4/5] cursor-pointer flex-col justify-end overflow-hidden rounded-2xl border border-white/5 bg-carbon p-5 shadow-xl transition-all duration-300 hover:border-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber md:aspect-[3/4] md:p-8 touch-manipulation">
                <div class="absolute inset-0 z-0">
                    <img src="{{ theme_platform_asset_url('marketing/experience-touring.png') }}" alt="Трасса" width="800" height="1000" sizes="(max-width:768px) 100vw, (max-width:1280px) 50vw, 33vw" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-[15s] ease-out group-hover:scale-105" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
                    <div class="w-full h-full bg-[#111113] hidden img-fallback relative overflow-hidden">
                        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-moto-amber/5 blur-[80px]"></div>
                    </div>
                </div>
                <div class="absolute inset-0 bg-black/10 group-hover:bg-transparent transition-colors duration-500 z-10"></div>
                <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-obsidian via-obsidian/80 to-transparent z-10"></div>
                
                <div class="relative z-20">
                    <span class="text-moto-amber text-[10px] sm:text-xs font-bold uppercase tracking-widest mb-3 block opacity-90">Для тех, кто не спешит</span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2 group-hover:text-moto-amber transition-colors drop-shadow-md leading-tight">Дальний<br>маршрут</h3>
                    <p class="text-zinc-300 text-[13px] sm:text-sm mb-5 line-clamp-2 md:line-clamp-3 leading-relaxed">Доехать до гор или Крыма без усталости — комфорт, кофры, дорога как удовольствие.</p>
                    <div class="mt-4 inline-flex items-center gap-2 px-6 py-2.5 bg-white/10 group-hover:bg-moto-amber text-white group-hover:text-[#0c0c0c] font-bold text-sm rounded-xl opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:translate-y-4 sm:group-hover:translate-y-0 transition-all duration-300 backdrop-blur-md border border-white/20 group-hover:border-moto-amber shadow-lg group-hover:shadow-moto-amber/20">
                        Выбрать маршрут <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
