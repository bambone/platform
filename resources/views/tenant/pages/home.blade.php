@php
    $bikeIdsJson = $bikes->pluck('id')->toJson();
    $sections = $sections ?? [];
    $homeLayoutSections = $homeLayoutSections ?? collect();
    $heroLcpImage = theme_platform_asset_url('marketing/hero-bg.png');
@endphp
@extends('tenant.layouts.app')

@push('tenant-preload')
    <link rel="preload" as="image" href="{{ $heroLcpImage }}">
@endpush

@section('content')
    <!-- Alpine App State -->
    <div x-data="globalBookingState()" data-bike-ids="{{ $bikeIdsJson }}" class="w-full min-w-0" x-init="$nextTick(() => { const s = Alpine.store('tenantBooking'); if (s.filters.start_date && s.filters.end_date) { setTimeout(() => s.applyCatalogSearch({ scrollToCatalog: false }), 0); } })">
        @forelse ($homeLayoutSections as $section)
            @include('tenant.pages.partials.home-section-slot', [
                'section' => $section,
                'bikes' => $bikes,
                'badges' => $badges,
                'faqs' => $faqs ?? collect(),
                'reviews' => $reviews ?? collect(),
            ])
        @empty
            {{-- Нет опубликованных секций главной (кроме main): запасной минимальный вид --}}
            <x-hero :section="$sections['hero'] ?? []" />
        @endforelse

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
