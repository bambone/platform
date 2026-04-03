@props([
    'bikes',
    'badges' => [],
    'heading' => 'Наш автопарк',
    'subheading' => null,
])

@php
    $subPlain = $subheading ?? 'Премиальная техника для любого стиля. Ограниченное количество мотоциклов — бронируйте заранее.';
@endphp

<section id="catalog" class="relative z-10 border-t border-white/[0.02] bg-[#0c0c0e] py-16 sm:py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-8">
        <div class="mb-10 flex w-full min-w-0 flex-col gap-4 border-b border-white/5 pb-6 sm:mb-12 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0 w-full max-w-full">
                <h2 class="mb-3 text-balance text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">{{ $heading }}</h2>
                <p class="max-w-2xl text-sm leading-relaxed text-silver/80 sm:text-base md:text-lg">{{ $subPlain }}</p>
            </div>
        </div>

        <template x-if="filteredBikes.length === 0">
            <div class="flex min-h-[min(400px,70vh)] w-full flex-col items-center justify-center rounded-2xl border border-white/10 bg-carbon p-8 text-center sm:p-12">
                <svg class="mb-4 h-16 w-16 text-silver/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mb-2 text-xl font-bold text-white">Нет свободных байков</h3>
                <p class="mb-8 max-w-md text-silver">На выбранные вами даты вся техника уже забронирована. Попробуйте изменить даты или локацию.</p>
                <button type="button" @click="resetFilters" class="min-h-11 rounded-xl border border-white/10 bg-white/5 px-6 py-3 text-white transition-colors hover:bg-white/10 active:scale-[0.98]">
                    Сбросить фильтры
                </button>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 md:grid-cols-2 md:gap-6 xl:grid-cols-3 xl:gap-8" x-show="filteredBikes.length > 0">
            @foreach ($bikes as $index => $bike)
                <div
                    class="min-w-0 transition-opacity duration-300"
                    x-show="isBikeVisible({{ $bike->id }})"
                    :class="{ 'opacity-[0.42]': isCatalogDimmed({{ $bike->id }}) }"
                    :style="{ order: catalogOrder({{ $bike->id }}) }"
                >
                    <x-bike-card :bike="$bike" :badge="$badges[$index] ?? null" />
                </div>
            @endforeach
        </div>
    </div>
</section>
