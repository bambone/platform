@extends('layouts.app')

@section('title', 'Бронирование — ' . $motorcycle->name)

@section('content')
<section class="py-16 container mx-auto px-4 max-w-4xl">
    <a href="{{ route('booking.index') }}" class="inline-flex items-center gap-2 text-silver hover:text-white mb-8 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Назад к каталогу
    </a>

    <div class="grid md:grid-cols-2 gap-8 mb-12">
        <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-carbon">
            @if($motorcycle->cover_url)
                <img src="{{ $motorcycle->cover_url }}" alt="{{ $motorcycle->name }}" class="w-full h-full object-cover">
            @endif
        </div>
        <div>
            <h1 class="text-2xl font-bold text-white mb-2">{{ $motorcycle->name }}</h1>
            <p class="text-silver mb-6">{{ $motorcycle->short_description }}</p>
            <div class="text-moto-amber font-bold text-xl">{{ number_format($motorcycle->price_per_day ?? 0) }} ₽ / сутки</div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 md:p-8" x-data="bookingForm({{ $motorcycle->id }}, {{ $rentalUnits->first()?->id ?? 'null' }}, @js($addons->map(fn($a) => ['id' => $a->id])->values()->all()))">
        <h2 class="text-xl font-bold text-white mb-6">Выберите даты</h2>

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm text-silver mb-2">Дата начала</label>
                <input type="date" x-model="startDate" @change="calculatePrice" :min="minDate"
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-moto-amber focus:border-moto-amber outline-none">
            </div>
            <div>
                <label class="block text-sm text-silver mb-2">Дата возврата</label>
                <input type="date" x-model="endDate" @change="calculatePrice" :min="minDate"
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-moto-amber focus:border-moto-amber outline-none">
            </div>
        </div>

        @if($addons->isNotEmpty())
            <div class="mb-6">
                <label class="block text-sm text-silver mb-3">Дополнительные опции</label>
                <div class="space-y-2">
                    @foreach($addons as $addon)
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="number" min="0" x-model.number="addons[{{ $addon->id }}]" @change="calculatePrice"
                                class="w-20 bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                            <span class="text-white">{{ $addon->name }}</span>
                            <span class="text-moto-amber">{{ number_format($addon->price) }} ₽</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div x-show="priceResult" x-cloak class="mb-6 p-4 bg-white/5 rounded-xl border border-white/5">
            <template x-if="priceResult && !priceResult.available">
                <p class="text-red-400" x-text="priceResult?.message || 'Даты заняты'"></p>
            </template>
            <template x-if="priceResult && priceResult.available">
                <div>
                    <div class="flex justify-between text-silver text-sm mb-1">
                        <span>Стоимость аренды</span>
                        <span class="text-white" x-text="formatMoney(priceResult?.price?.total || 0) + ' ₽'"></span>
                    </div>
                    <div class="flex justify-between text-silver text-sm mb-2" x-show="priceResult?.price?.deposit > 0">
                        <span>Залог</span>
                        <span class="text-white" x-text="formatMoney(priceResult?.price?.deposit || 0) + ' ₽'"></span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-white/10">
                        <span class="font-bold text-white">Итого</span>
                        <span class="text-2xl font-bold text-moto-amber" x-text="formatMoney(priceResult?.price?.total || 0) + ' ₽'"></span>
                    </div>
                </div>
            </template>
        </div>

        <button @click="proceedToCheckout" :disabled="!canProceed || loading"
            class="w-full bg-moto-amber hover:bg-moto-amber/90 text-white font-bold py-4 rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
            <span x-show="!loading">Перейти к оформлению</span>
            <span x-show="loading">Проверка...</span>
        </button>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingForm', (motorcycleId, rentalUnitId, addonsList) => ({
        motorcycleId,
        rentalUnitId,
        startDate: '',
        endDate: '',
        addons: Object.fromEntries(addonsList.map(a => [a.id, 0])),
        priceResult: null,
        loading: false,
        minDate: new Date().toISOString().split('T')[0],

        get canProceed() {
            return this.startDate && this.endDate && this.priceResult?.available;
        },

        formatMoney(n) {
            return new Intl.NumberFormat('ru-RU').format(n);
        },

        async calculatePrice() {
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
            if (!this.canProceed) return;
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
