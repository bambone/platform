@props(['bike', 'badge' => null, 'useBookingContext' => true])
@php
    $card = $bike->catalogCardForView();
    $positioning = $card['positioning'];
    $scenario = $card['scenario'];
    $highlights = $card['highlights'];
    $priceNote = $card['price_note'];
    $imageUrl = $bike->cover_url ?? null;
    $type = $bike->model ?? $bike->type ?? '';
    $engine = $bike->engine_cc ?? $bike->engine ?? 0;
    $detailUrl = route('motorcycle.show', $bike->slug);
    $detailLabel = 'О модели: ' . $bike->name;
@endphp
<article class="group/card relative flex h-full flex-col overflow-hidden rounded-2xl border border-white/5 bg-carbon shadow-lg shadow-black/30 transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-0.5 hover:border-white/10 hover:shadow-xl hover:shadow-black/40 focus-within:border-white/12 focus-within:shadow-xl">
    <div class="pointer-events-none absolute inset-0 -z-10 rounded-2xl bg-white/[0.02] opacity-0 blur-2xl transition-opacity duration-300 group-hover/card:opacity-100"></div>

    <a href="{{ $detailUrl }}"
       class="relative block h-52 shrink-0 overflow-hidden border-b border-white/[0.04] bg-[#0a0a0c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber sm:h-60 md:h-64"
       aria-label="{{ $detailLabel }}">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $bike->name }}" width="800" height="512" sizes="(max-width:640px) 100vw, (max-width:1024px) 50vw, 33vw" class="block h-full w-full object-cover transition-transform duration-700 ease-out group-hover/card:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover/card:scale-100" loading="lazy" decoding="async" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden')">
        @endif
        <div class="img-fallback absolute inset-0 flex items-center justify-center text-sm text-silver {{ $imageUrl ? 'hidden' : '' }}">
            <svg class="h-12 w-12 text-white/5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="pointer-events-none absolute inset-0 bg-black/15 transition-colors duration-500 group-hover/card:bg-black/5"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-carbon to-transparent"></div>
        <div class="pointer-events-none absolute left-4 right-4 top-4 z-10 flex flex-wrap gap-2">
            @if($badge)
                <span class="rounded-full bg-moto-amber/95 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-[#0c0c0c] shadow-md">{{ $badge }}</span>
            @endif
            @if($type)
                <span class="rounded-full border border-white/10 bg-black/55 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-200/95 backdrop-blur-md">{{ $type }}</span>
            @endif
        </div>
    </a>

    <div class="relative z-10 flex min-h-0 flex-1 flex-col bg-carbon px-4 pb-5 pt-4 sm:px-5 sm:pb-5">
        {{-- 1. Название --}}
        <h3 class="mb-2 min-h-[1.5rem] text-lg font-bold leading-tight text-white sm:min-h-[1.75rem] sm:text-[22px]">
            <a href="{{ $detailUrl }}"
               class="line-clamp-2 rounded-sm text-inherit decoration-transparent underline-offset-2 transition-colors hover:text-white hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber"
               title="{{ $bike->name }}">{{ $bike->name }}</a>
        </h3>

        {{-- 2. Позиционирование (+ сценарий спокойно в той же зоне) --}}
        <div class="mb-3 min-h-[2.75rem]">
            <p class="line-clamp-2 text-sm leading-relaxed text-zinc-300">
                @if($scenario !== '')
                    <span class="text-zinc-400">{{ $scenario }}</span>
                @endif
                @if($scenario !== '' && $positioning !== '')
                    <span class="text-zinc-500"> · </span>
                @endif
                @if($positioning !== '')
                    <span class="text-zinc-200">{{ $positioning }}</span>
                @endif
            </p>
        </div>

        {{-- 3. Чипы --}}
        @if($highlights !== [])
            <div class="mb-3 flex min-h-[1.5rem] flex-nowrap gap-1.5 overflow-hidden" role="list" aria-label="Кратко о модели">
                @foreach($highlights as $chip)
                    <span role="listitem" class="max-w-[34%] shrink-0 truncate rounded-md border border-white/[0.1] bg-white/[0.05] px-2 py-0.5 text-center text-[11px] font-medium leading-tight text-zinc-200">{{ $chip }}</span>
                @endforeach
            </div>
        @else
            <div class="mb-3 min-h-[1.5rem]" aria-hidden="true"></div>
        @endif

        <div class="min-h-2 flex-1" aria-hidden="true"></div>

        {{-- 4. Характеристики --}}
        <div class="-mx-4 mb-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-white/[0.05] px-4 pt-3 text-[12px] font-medium text-zinc-400 sm:-mx-5 sm:px-5">
            <div class="flex shrink-0 items-center gap-1.5">
                <svg class="h-3.5 w-3.5 shrink-0 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span class="text-zinc-200">{{ $engine }} cc</span>
            </div>
            @if(filled($bike->transmission))
                <div class="flex min-w-0 max-w-full items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 shrink-0 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    <span class="truncate text-zinc-200" title="{{ $bike->transmission }}">{{ $bike->transmission }}</span>
                </div>
            @endif
        </div>

        {{-- 5. Цена --}}
        <div class="mb-4 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-3 sm:px-3.5">
            @if($useBookingContext)
                <div x-show="!$store.tenantBooking.filters.start_date || !$store.tenantBooking.filters.end_date" class="min-w-0">
                    <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wider text-zinc-500">от</span>
                    <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0">
                        <span class="text-[1.65rem] font-extrabold leading-none tracking-tight text-white sm:text-[2rem]">{{ number_format($bike->price_per_day, 0, ',', ' ') }}</span>
                        <span class="text-base font-bold text-moto-amber">₽</span>
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">/ сутки</span>
                    </div>
                    @if($priceNote !== '')
                        <p class="mt-1.5 line-clamp-1 text-xs leading-tight text-zinc-400">{{ $priceNote }}</p>
                    @endif
                </div>
                <div class="min-w-0" x-show="$store.tenantBooking.filters.start_date && $store.tenantBooking.filters.end_date" x-cloak>
                    <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-zinc-500" x-text="`${$store.tenantBooking.rentalDayCount()} дней аренды`"></span>
                    <div class="flex flex-wrap items-baseline gap-x-1.5">
                        <span class="text-[1.65rem] font-extrabold leading-none tracking-tight text-white sm:text-[2rem]"><span x-text="formatPrice(calculateCardTotalPrice({{ $bike->price_per_day }}))"></span></span>
                        <span class="text-base font-bold text-moto-amber">₽</span>
                    </div>
                    @if($priceNote !== '')
                        <p class="mt-1.5 line-clamp-1 text-xs text-zinc-400">{{ $priceNote }}</p>
                    @endif
                </div>
            @else
                <div class="min-w-0">
                    <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wider text-zinc-500">от</span>
                    <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0">
                        <span class="text-[1.65rem] font-extrabold leading-none tracking-tight text-white sm:text-[2rem]">{{ number_format($bike->price_per_day, 0, ',', ' ') }}</span>
                        <span class="text-base font-bold text-moto-amber">₽</span>
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">/ сутки</span>
                    </div>
                    @if($priceNote !== '')
                        <p class="mt-1.5 line-clamp-1 text-xs leading-tight text-zinc-400">{{ $priceNote }}</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- 6. CTA --}}
        <div class="mt-auto flex flex-col gap-2 sm:flex-row sm:gap-2.5">
            <a href="{{ $detailUrl }}"
               class="tenant-btn-secondary w-full flex-1 sm:w-auto touch-manipulation">
                О модели
            </a>
            @if($useBookingContext)
                {{-- Весь PHP-пейлоад через @js (безопасно в двойных кавычках); даты из Alpine через Object.assign. Нельзя оборачивать весь @click в '…' — апостроф в названии байка рвёт атрибут. --}}
                <button type="button"
                        class="tenant-btn-primary w-full flex-1 gap-2 sm:w-auto touch-manipulation"
                        @click.stop="$dispatch('open-booking-modal', Object.assign({}, @js(['id' => $bike->id, 'name' => $bike->name, 'price' => $bike->price_per_day]), { start: $store.tenantBooking.filters.start_date, end: $store.tenantBooking.filters.end_date }))">
                    Забронировать
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            @else
                <button type="button"
                        class="tenant-btn-primary w-full flex-1 gap-2 sm:w-auto touch-manipulation"
                        @click.stop="$dispatch('open-booking-modal', @js(['id' => $bike->id, 'name' => $bike->name, 'price' => $bike->price_per_day, 'start' => '', 'end' => '']))">
                    Забронировать
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            @endif
        </div>
    </div>
</article>
