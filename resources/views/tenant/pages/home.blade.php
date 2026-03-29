@php
    $bikeIdsJson = $bikes->pluck('id')->toJson();
    $sections = $sections ?? [];
@endphp
@extends('tenant.layouts.app')

@section('content')
    <!-- Alpine App State -->
    <div x-data="globalBookingState()" data-bike-ids="{{ $bikeIdsJson }}">
        
        <!-- Extracted Hero Component -->
        <x-hero :section="$sections['hero'] ?? null" />

        <x-experience-block :section="$sections['route_cards'] ?? null" />

        <!-- Catalog Section -->
        <section id="catalog" class="relative z-10 border-t border-white/[0.02] bg-[#0c0c0e] py-16 sm:py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
                
                <div class="mb-10 flex flex-col gap-4 border-b border-white/5 pb-6 sm:mb-12 md:flex-row md:items-end md:justify-between">
                    <div class="min-w-0">
                        <h2 class="mb-3 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">Наш автопарк</h2>
                        <p class="max-w-2xl text-sm leading-relaxed text-silver/80 sm:text-base md:text-lg">Премиальная техника для любого стиля. <span class="text-moto-amber/90 font-medium">Ограниченное количество мотоциклов</span> — бронируйте заранее.</p>
                    </div>
                </div>

                <!-- Empty State -->
                <template x-if="filteredBikes.length === 0">
                    <div class="flex min-h-[min(400px,70vh)] w-full flex-col items-center justify-center rounded-2xl border border-white/10 bg-carbon p-8 text-center sm:p-12">
                        <svg class="w-16 h-16 text-silver/30 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-xl font-bold text-white mb-2">Нет свободных байков</h3>
                        <p class="text-silver mb-8 max-w-md">На выбранные вами даты вся техника уже забронирована. Попробуйте изменить даты или локацию.</p>
                        <button type="button" @click="resetFilters" class="min-h-11 rounded-xl border border-white/10 bg-white/5 px-6 py-3 text-white transition-colors hover:bg-white/10 active:scale-[0.98]">
                            Сбросить фильтры
                        </button>
                    </div>
                </template>

                <!-- Bikes Grid -->
                <div class="grid grid-cols-1 gap-5 sm:gap-6 md:grid-cols-2 md:gap-6 xl:grid-cols-3 xl:gap-8" x-show="filteredBikes.length > 0">
                    @foreach($bikes as $index => $bike)
                        <div x-show="isBikeVisible({{ $bike->id }})">
                            <x-bike-card :bike="$bike" :badge="$badges[$index] ?? null" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <x-why-us :section="$sections['why_us'] ?? null" />

        <x-how-it-works :section="$sections['how_it_works'] ?? null" />

        <x-rental-conditions :section="$sections['rental_conditions'] ?? null" />

        <x-social-proof :section="$sections['reviews_block'] ?? null" :reviews="$reviews ?? []" />

        <x-faq-block :section="$sections['faq_block'] ?? null" :faqs="$faqs ?? []" />

        <x-final-cta :section="$sections['final_cta'] ?? null" />

        <x-booking-modal />
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('globalBookingState', () => {
            const el = document.querySelector('[data-bike-ids]');
            const bikeIds = el ? JSON.parse(el.getAttribute('data-bike-ids')) : [];
            return {
                filters: { start_date: '', end_date: '', location: '' },
                isSearching: false,
                allBikes: bikeIds,
                filteredBikes: [...bikeIds],

            applySearch() {
                if (!this.filters.start_date || !this.filters.end_date) {
                    const el = document.getElementById('start_date');
                    if (el) el.focus();
                    return;
                }
                
                this.isSearching = true;
                
                // Simulate network filtering
                setTimeout(() => {
                    document.getElementById('catalog').scrollIntoView({behavior: 'smooth'});
                    this.isSearching = false;
                }, 400);
            },

            resetFilters() {
                this.filters.start_date = '';
                this.filters.end_date = '';
                this.filteredBikes = this.allBikes;
            },

            isBikeVisible(id) {
                return this.filteredBikes.includes(id);
            },

            formatDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                return d.toLocaleDateString('ru-RU', {day: '2-digit', month: '2-digit'});
            },

            formatPrice(amount) {
                return new Intl.NumberFormat('ru-RU').format(amount);
            },

            calculateCardTotalPrice(pricePerDay) {
                if (!this.filters.start_date || !this.filters.end_date) return 0;
                const start = new Date(this.filters.start_date);
                const end = new Date(this.filters.end_date);
                if (end < start) return 0;
                
                const MS_PER_DAY = 1000 * 60 * 60 * 24;
                const utc1 = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
                const utc2 = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());
                
                const days = Math.floor((utc2 - utc1) / MS_PER_DAY) + 1;
                return days * pricePerDay;
            }
        };
        });
    });
    </script>
@endsection
