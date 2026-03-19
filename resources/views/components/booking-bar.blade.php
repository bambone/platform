<form @submit.prevent="applySearch" class="w-full max-w-5xl relative z-20">
    <div class="bg-black/25 backdrop-blur-md border border-white/10 rounded-2xl p-4 md:p-5">
        <div class="flex flex-col lg:flex-row gap-3 lg:gap-4 lg:items-end">
            <div class="flex-1 min-w-0">
                <label for="location" class="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-widest">Город</label>
                <div class="relative">
                    <select id="location" x-model="filters.location" class="w-full bg-black/50 border border-white/10 rounded-xl pl-4 pr-10 py-3 text-white text-base focus:bg-black/60 focus:ring-2 focus:ring-moto-amber/40 focus:border-moto-amber/30 outline-none transition-all appearance-none cursor-pointer hover:border-white/20 h-12" title="Геленджик, Анапа или Новороссийск">
                        <option value="">Где катать?</option>
                        <option>Геленджик</option>
                        <option>Анапа</option>
                        <option>Новороссийск</option>
                    </select>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-white/55">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <label for="start_date" class="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-widest">С</label>
                <input type="date" id="start_date" x-model="filters.start_date" required min="{{ date('Y-m-d') }}" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white text-base focus:bg-black/60 focus:ring-2 focus:ring-moto-amber/40 focus:border-moto-amber/30 outline-none transition-all hover:border-white/20 h-12 [color-scheme:dark]">
            </div>

            <div class="flex-1 min-w-0">
                <label for="end_date" class="block text-xs font-semibold text-white/60 mb-1.5 uppercase tracking-widest">По</label>
                <input type="date" id="end_date" x-model="filters.end_date" required min="{{ date('Y-m-d') }}" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white text-base focus:bg-black/60 focus:ring-2 focus:ring-moto-amber/40 focus:border-moto-amber/30 outline-none transition-all hover:border-white/20 h-12 [color-scheme:dark]">
            </div>

            <div class="lg:shrink-0">
                <label class="hidden lg:block text-xs mb-1.5 opacity-0 pointer-events-none" aria-hidden="true">&nbsp;</label>
                <button type="submit"
                        :disabled="isSearching"
                        :class="isSearching ? 'opacity-70 cursor-not-allowed' : 'hover:-translate-y-0.5 hover:shadow-xl hover:shadow-orange-500/30 active:scale-[0.97]'"
                        class="w-full lg:w-auto bg-gradient-to-r from-moto-amber to-orange-600 text-white px-8 py-3 rounded-xl font-bold text-base transition-all flex items-center justify-center gap-2 h-12 whitespace-nowrap shadow-lg shadow-orange-500/20">
                    <template x-if="!isSearching">
                        <span class="flex items-center gap-2">
                            <span class="hidden xl:inline">Забронировать сейчас</span>
                            <span class="xl:hidden">Забронировать</span>
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </span>
                    </template>
                    <template x-if="isSearching">
                        <span class="flex items-center gap-2">
                            <svg class="animate-spin w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Поиск...
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
    <p class="text-center text-[11px] text-white/40 mt-2.5 tracking-wide">Ограниченное количество мотоциклов — бронируйте заранее</p>
</form>
