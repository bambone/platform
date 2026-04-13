@extends('tenant.layouts.app')

@section('content')
@php
    $visibleAtSelectedLocation = $visibleAtSelectedLocation ?? true;
@endphp
<section class="mx-auto max-w-4xl px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
    <a href="{{ route('booking.index') }}" class="mb-6 inline-flex min-h-10 items-center gap-2 text-sm text-silver transition-colors hover:text-white sm:mb-8 sm:text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Назад к каталогу
    </a>

    @include('tenant.partials.catalog-location-filter', [
        'catalogLocations' => $catalogLocations ?? collect(),
        'selectedCatalogLocation' => $selectedCatalogLocation ?? null,
        'catalogLocationFormAction' => $catalogLocationFormAction ?? route('booking.show', $motorcycle->slug),
    ])

    @if (! $visibleAtSelectedLocation)
        <div class="mb-6 rounded-xl border border-amber-500/35 bg-amber-950/35 p-4 text-sm leading-relaxed text-amber-100 sm:text-base" role="status">
            В выбранной точке эта модель сейчас недоступна. Выберите другую локацию выше или откройте <a href="{{ route('booking.index', ['location' => 'all']) }}" class="font-semibold text-moto-amber underline-offset-2 hover:underline">весь каталог бронирования</a>.
        </div>
    @endif

    <div class="mb-8 grid grid-cols-1 gap-6 sm:mb-10 md:grid-cols-2 md:gap-8">
        <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-carbon">
            @if($motorcycle->publicCoverUrl())
                <img src="{{ $motorcycle->publicCoverUrl() }}" alt="{{ $motorcycle->name }}" class="h-full w-full object-cover">
            @endif
        </div>
        <div class="min-w-0">
            <h1 class="mb-2 text-balance text-xl font-bold text-white sm:text-2xl">{{ ($resolvedSeo ?? null)?->h1 ?? $motorcycle->name }}</h1>
            <p class="mb-6 text-sm leading-relaxed text-silver sm:text-base">{{ $motorcycle->short_description }}</p>
            <div class="break-words text-lg font-bold text-moto-amber sm:text-xl">{{ number_format($motorcycle->price_per_day ?? 0) }} ₽ / сутки</div>
        </div>
    </div>

    <div class="glass rounded-2xl p-4 sm:p-6 md:p-8" x-data="bookingForm({{ $motorcycle->id }}, {{ $rentalUnits->first()?->id ?? 'null' }}, @js($addons->map(fn($a) => ['id' => $a->id])->values()->all()), {{ $visibleAtSelectedLocation ? 'false' : 'true' }})">
        <h2 class="mb-5 text-lg font-bold text-white sm:mb-6 sm:text-xl">Выберите даты</h2>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2">
            <div class="min-w-0">
                <label class="mb-2 block text-sm text-silver" for="booking-start-date">Дата начала</label>
                <input id="booking-start-date" name="rental_start_date" type="date" x-model="startDate" @change="calculatePrice" :min="minDate" autocomplete="off"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber [color-scheme:dark]">
            </div>
            <div class="min-w-0">
                <label class="mb-2 block text-sm text-silver" for="booking-end-date">Дата возврата</label>
                <input id="booking-end-date" name="rental_end_date" type="date" x-model="endDate" @change="calculatePrice" :min="minDate" autocomplete="off"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber [color-scheme:dark]">
            </div>
        </div>

        @if($addons->isNotEmpty())
            <div class="mb-6">
                <span class="mb-3 block text-sm text-silver">Дополнительные опции</span>
                <div class="space-y-3">
                    @foreach($addons as $addon)
                        <label for="booking-addon-qty-{{ $addon->id }}" class="flex cursor-pointer flex-col gap-2 rounded-xl border border-white/5 bg-white/[0.02] p-3 sm:flex-row sm:items-center sm:gap-3 sm:border-0 sm:bg-transparent sm:p-0">
                            <div class="flex items-center gap-3">
                                <input id="booking-addon-qty-{{ $addon->id }}" name="addon_quantity[{{ $addon->id }}]" type="number" min="0" x-model.number="addons[{{ $addon->id }}]" @change="calculatePrice" inputmode="numeric" autocomplete="off"
                                    class="h-11 w-20 shrink-0 rounded-lg border border-white/10 bg-black/50 px-3 py-2 text-sm text-white">
                                <span class="min-w-0 flex-1 text-sm text-white sm:text-base">{{ $addon->name }}</span>
                            </div>
                            <span class="shrink-0 text-moto-amber sm:ml-auto">{{ number_format($addon->price) }} ₽</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div x-show="priceResult" x-cloak class="mb-6 rounded-xl border border-white/5 bg-white/5 p-4">
            <template x-if="priceResult && !priceResult.available">
                <p class="text-sm text-red-400 sm:text-base" x-text="priceResult?.message || 'Даты заняты'"></p>
            </template>
            <template x-if="priceResult && priceResult.available">
                <div>
                    <div class="mb-1 flex justify-between text-xs text-silver sm:text-sm">
                        <span>Стоимость аренды</span>
                        <span class="text-white" x-text="formatMoney(priceResult?.price?.total || 0) + ' ₽'"></span>
                    </div>
                    <div class="mb-2 flex justify-between text-xs text-silver sm:text-sm" x-show="priceResult?.price?.deposit > 0">
                        <span>Залог</span>
                        <span class="text-white" x-text="formatMoney(priceResult?.price?.deposit || 0) + ' ₽'"></span>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/10 pt-2">
                        <span class="font-bold text-white">Итого</span>
                        <span class="text-xl font-bold text-moto-amber sm:text-2xl" x-text="formatMoney(priceResult?.price?.total || 0) + ' ₽'"></span>
                    </div>
                </div>
            </template>
        </div>

        <button type="button" @click="proceedToCheckout" :disabled="!canProceed || loading"
            class="tenant-btn-primary min-h-12 w-full gap-2 py-3.5 touch-manipulation disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-14 sm:py-4">
            <span x-show="!loading">Перейти к оформлению</span>
            <span x-show="loading">Проверка...</span>
        </button>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingForm', (motorcycleId, rentalUnitId, addonsList, locationBlocked = false) => ({
        motorcycleId,
        rentalUnitId,
        locationBlocked,
        startDate: '',
        endDate: '',
        addons: Object.fromEntries(addonsList.map(a => [a.id, 0])),
        priceResult: null,
        loading: false,
        minDate: new Date().toISOString().split('T')[0],

        get canProceed() {
            if (this.locationBlocked) return false;
            return this.startDate && this.endDate && this.priceResult?.available;
        },

        formatMoney(n) {
            return new Intl.NumberFormat('ru-RU').format(n);
        },

        async calculatePrice() {
            if (this.locationBlocked) return;
            if (!this.startDate || !this.endDate) return;
            this.loading = true;
            try {
                const addonsPayload = {};
                for (const [id, qty] of Object.entries(this.addons)) {
                    if (qty > 0) addonsPayload[id] = qty;
                }
                const r = await fetch('{{ route("booking.calculate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        motorcycle_id: this.motorcycleId,
                        rental_unit_id: this.rentalUnitId,
                        start_date: this.startDate,
                        end_date: this.endDate,
                        addons: addonsPayload
                    })
                });
                const data = await r.json();
                this.priceResult = data;
            } catch (e) {
                this.priceResult = { available: false, message: 'Ошибка расчёта' };
            } finally {
                this.loading = false;
            }
        },

        async proceedToCheckout() {
            if (this.locationBlocked || !this.canProceed) return;
            this.loading = true;
            try {
                const addonsPayload = {};
                for (const [id, qty] of Object.entries(this.addons)) {
                    if (qty > 0) addonsPayload[id] = qty;
                }
                const r = await fetch('{{ route("booking.store-draft") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        motorcycle_id: this.motorcycleId,
                        rental_unit_id: this.rentalUnitId,
                        start_date: this.startDate,
                        end_date: this.endDate,
                        addons: addonsPayload
                    })
                });
                const data = await r.json();
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    this.priceResult = { available: false, message: data.message || 'Ошибка' };
                }
            } catch (e) {
                this.priceResult = { available: false, message: 'Ошибка отправки' };
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>
@endsection
