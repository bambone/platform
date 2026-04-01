@php
    $bikeIdsJson = $bikes->pluck('id')->toJson();
    $sections = $sections ?? [];
    $themeHeroLcp = tenant_theme_public_url('site/marketing/hero-bg.png');
    $heroLcpImage = $themeHeroLcp !== '' ? $themeHeroLcp : theme_platform_asset_url('marketing/hero-bg.png');
@endphp
@extends('tenant.layouts.app')

@push('tenant-preload')
    <link rel="preload" as="image" href="{{ $heroLcpImage }}">
@endpush

@section('content')
    <!-- Alpine App State -->
    <div x-data="globalBookingState()" data-bike-ids="{{ $bikeIdsJson }}" class="w-full min-w-0" x-init="$nextTick(() => { const s = Alpine.store('tenantBooking'); if (s.filters.start_date && s.filters.end_date) { s.applyCatalogSearch({ scrollToCatalog: false }); } })">
        
        <!-- Extracted Hero Component -->
        <x-hero :section="$sections['hero'] ?? null" />

        <x-experience-block :section="$sections['route_cards'] ?? null" />

        <!-- Catalog Section -->
        <section id="catalog" class="relative z-10 border-t border-white/[0.02] bg-[#0c0c0e] py-16 sm:py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
                
                <div class="mb-10 flex w-full min-w-0 flex-col gap-4 border-b border-white/5 pb-6 sm:mb-12 md:flex-row md:items-end md:justify-between">
                    <div class="min-w-0 w-full max-w-full">
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
                        <div class="min-w-0 transition-opacity duration-300"
                             x-show="isBikeVisible({{ $bike->id }})"
                             :class="{ 'opacity-[0.42]': isCatalogDimmed({{ $bike->id }}) }"
                             :style="{ order: catalogOrder({{ $bike->id }}) }">
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
                allBikes: bikeIds,
                filteredBikes: [...bikeIds],

            resetFilters() {
                const s = Alpine.store('tenantBooking');
                s.filters.start_date = '';
                s.filters.end_date = '';
                s.filters.location = '';
                s.catalogAvailability = null;
                s.persist();
                window.TenantDatePickers?.clearBar?.();
                this.filteredBikes = this.allBikes;
            },

            catalogOrder(id) {
                const m = Alpine.store('tenantBooking').catalogAvailability;
                if (m == null || typeof m !== 'object') {
                    return 0;
                }
                const ok = m[String(id)] === true || m[id] === true;

                return ok ? 0 : 1;
            },

            isCatalogDimmed(id) {
                const m = Alpine.store('tenantBooking').catalogAvailability;
                if (m == null || typeof m !== 'object') {
                    return false;
                }

                return ! (m[String(id)] === true || m[id] === true);
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
                return Alpine.store('tenantBooking').calculateCardTotalPrice(pricePerDay);
            }
        };
        });
    });
    </script>
@endsection
