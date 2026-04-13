@props(['bike'])
@php
    $imageUrl = $bike->publicCoverUrl();
    $detailUrl = route('motorcycle.show', $bike->slug);
    $card = $bike->catalogCardForView();
    $oneLine = trim((string) ($bike->short_description ?? ''));
    if ($oneLine === '') {
        $parts = array_filter([$card['scenario'] ?? '', $card['positioning'] ?? '']);
        $oneLine = implode(' · ', $parts);
    }
@endphp
<article class="group/rel flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-carbon/90 shadow-lg shadow-black/30 transition-[border-color,box-shadow] duration-300 hover:border-white/18 hover:shadow-xl">
    <a href="{{ $detailUrl }}" class="relative block aspect-[16/10] shrink-0 overflow-hidden bg-[#0a0a0c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber" aria-label="Фото: {{ $bike->name }}">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="" width="640" height="400" sizes="(max-width:640px) 100vw, (max-width:1024px) 50vw, 33vw" class="h-full w-full object-cover transition-transform duration-500 group-hover/rel:scale-[1.03] motion-reduce:group-hover/rel:scale-100" loading="lazy" decoding="async" fetchpriority="low">
        @else
            <div class="flex h-full w-full items-center justify-center text-zinc-600">
                <svg class="h-10 w-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        @endif
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-carbon/80 via-transparent to-transparent"></div>
    </a>
    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <h3 class="mb-1.5 text-base font-bold leading-snug text-white">
            <a href="{{ $detailUrl }}" class="line-clamp-2 rounded-sm decoration-transparent underline-offset-2 transition-colors hover:text-moto-amber/95 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">{{ $bike->name }}</a>
        </h3>
        <p class="mb-4 min-h-[1.25rem] line-clamp-1 text-sm leading-snug text-zinc-300">{{ $oneLine !== '' ? $oneLine : '—' }}</p>
        <p class="mb-4 text-lg font-extrabold tracking-tight text-white">
            {{ number_format($bike->price_per_day, 0, ',', ' ') }} <span class="text-moto-amber">₽</span>
            <span class="text-sm font-semibold text-zinc-400">/ сутки</span>
        </p>
        <a href="{{ $detailUrl }}"
           class="tenant-btn-primary mt-auto w-full touch-manipulation">
            Подробнее
        </a>
    </div>
</article>
