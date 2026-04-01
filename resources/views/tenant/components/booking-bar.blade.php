<form @submit.prevent="$store.tenantBooking.applyCatalogSearch()"
      x-init="$nextTick(() => window.TenantDatePickers?.initBar?.())"
      class="relative z-20 w-full max-w-5xl">
    <div class="rounded-2xl border border-white/10 bg-black/25 p-4 backdrop-blur-md md:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4">
            <div class="min-w-0 flex-1">
                <label for="location" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-white/60">Город</label>
                <div class="relative">
                    <select id="location" name="rental_location" x-model="$store.tenantBooking.filters.location" @change="$store.tenantBooking.onLocationChange()" class="h-12 w-full cursor-pointer appearance-none rounded-xl border border-white/10 bg-black/50 py-3 pl-4 pr-10 text-base text-white outline-none transition-all hover:border-white/20 focus:border-moto-amber/30 focus:bg-black/60 focus:ring-2 focus:ring-moto-amber/40" title="Геленджик, Анапа или Новороссийск">
                        <option value="">Где катать?</option>
                        <option>Геленджик</option>
                        <option>Анапа</option>
                        <option>Новороссийск</option>
                    </select>
                    <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-white/55">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <label for="start_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-white/60">С</label>
                <input type="text"
                       id="start_date"
                       data-fp-anchor="tenant-bar-start"
                       name="rental_start_date"
                       readonly
                       required
                       data-fp-min="{{ date('Y-m-d') }}"
                       placeholder="__.__.____"
                       autocomplete="off"
                       class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none transition-all [color-scheme:dark] hover:border-white/20 focus:border-moto-amber/30 focus:bg-black/60 focus:ring-2 focus:ring-moto-amber/40">
            </div>

            <div class="min-w-0 flex-1">
                <label for="end_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-white/60">По</label>
                <input type="text"
                       id="end_date"
                       data-fp-anchor="tenant-bar-end"
                       name="rental_end_date"
                       readonly
                       required
                       data-fp-min="{{ date('Y-m-d') }}"
                       placeholder="__.__.____"
                       autocomplete="off"
                       class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none transition-all [color-scheme:dark] hover:border-white/20 focus:border-moto-amber/30 focus:bg-black/60 focus:ring-2 focus:ring-moto-amber/40">
            </div>

            <div class="lg:shrink-0">
                {{-- Spacer for lg alignment with labeled fields; not a form label (avoids a11y "label without control"). --}}
                <div class="mb-1.5 hidden text-xs opacity-0 select-none pointer-events-none lg:block" aria-hidden="true">&nbsp;</div>
                <button type="submit"
                        :disabled="$store.tenantBooking.isSearching"
                        :class="$store.tenantBooking.isSearching ? 'cursor-not-allowed opacity-70' : 'hover:-translate-y-0.5 hover:shadow-xl hover:shadow-moto-amber/35 active:scale-[0.97]'"
                        class="tenant-btn-primary min-h-12 w-full whitespace-nowrap px-8 text-base lg:w-auto">
                    <template x-if="!$store.tenantBooking.isSearching">
                        <span class="flex items-center gap-2">
                            <span class="hidden xl:inline">Забронировать сейчас</span>
                            <span class="xl:hidden">Забронировать</span>
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </span>
                    </template>
                    <template x-if="$store.tenantBooking.isSearching">
                        <span class="flex items-center gap-2">
                            <svg class="h-5 w-5 animate-spin text-[#0c0c0c]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Поиск...
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
    <p class="mt-2.5 text-center text-[11px] tracking-wide text-white/40">Ограниченное количество мотоциклов — бронируйте заранее</p>
</form>
