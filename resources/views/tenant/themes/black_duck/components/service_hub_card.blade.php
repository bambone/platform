{{-- book_url: заявка с префиллом направления в форме /contacts; cta_url — посадочная. На карточке «Запись» ведём сразу в форму (а не ?book=1 на услуге). --}}
@php
    $it = is_array($it ?? null) ? $it : [];
    $img = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($it['image_url'] ?? '');
    $title = (string) ($it['title'] ?? '');
    $imgAlt = $title !== '' ? 'Фото: '.$title : 'Услуга';
    $sub = trim((string) ($it['card_subtitle'] ?? ''));
    $detail = trim((string) ($it['cta_url'] ?? ''));
    $book = trim((string) ($it['book_url'] ?? ''));
    $mode = (string) ($it['booking_mode'] ?? '');
    $price = trim((string) ($it['price_from'] ?? ''));
    $duration = trim((string) ($it['duration'] ?? ''));
    $modeLabel = match ($mode) {
        'instant' => 'Онлайн-слот',
        'quote' => 'Расчёт',
        default => 'По записи',
    };
    $sameLeadTarget = $book !== '' && $detail !== '' && $book === $detail;
@endphp
<li class="h-full min-h-0">
    <article class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-white/[0.07] to-white/[0.02] shadow-lg shadow-black/25 ring-1 ring-inset ring-white/[0.04]">
        @if ($detail !== '')
            <a href="{{ e($detail) }}" class="group relative block shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-[#36C7FF] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0c12]">
        @else
            <div class="relative shrink-0">
        @endif
        @if (filled($img))
            <div class="relative aspect-[16/10] w-full overflow-hidden">
                <img
                    src="{{ $img }}"
                    alt="{{ $imgAlt }}"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                    loading="lazy"
                    decoding="async"
                />
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent" aria-hidden="true"></div>
                <span class="pointer-events-none absolute left-3 top-3 rounded-full bg-black/55 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-white/95 ring-1 ring-white/15 backdrop-blur-sm sm:left-4 sm:top-4 sm:text-xs">
                    {{ $modeLabel }}
                </span>
            </div>
        @else
            <div class="flex aspect-[16/10] w-full items-center justify-center bg-white/[0.04] text-sm text-zinc-500">
                Фото появится после импорта
            </div>
        @endif
        @if ($detail !== '')
            </a>
        @else
            </div>
        @endif

        <div class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
            @if ($detail !== '')
                <a href="{{ e($detail) }}" class="rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-[#36C7FF] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0c12]">
                    <h3 class="text-lg font-semibold leading-snug text-white transition hover:text-[#7ddbff]">{{ $title }}</h3>
                </a>
            @else
                <h3 class="text-lg font-semibold leading-snug text-white">{{ $title }}</h3>
            @endif

            @if ($sub !== '')
                <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-zinc-400">{{ $sub }}</p>
            @endif

            <dl class="mt-4 grid gap-1.5 text-sm text-zinc-300">
                @if ($price !== '')
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <dt class="sr-only">Цена</dt>
                        <dd class="font-medium text-zinc-100">{{ $price }}</dd>
                    </div>
                @endif
                @if ($duration !== '')
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <dt class="text-zinc-500">Срок</dt>
                        <dd>{{ $duration }}</dd>
                    </div>
                @endif
            </dl>

            <div class="mt-auto flex flex-col gap-2.5 border-t border-white/10 pt-4 sm:flex-row sm:items-stretch">
                @if ($sameLeadTarget)
                    <a
                        href="{{ e($detail) }}"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#36C7FF] px-4 text-center text-sm font-semibold text-carbon shadow-md shadow-black/20 transition hover:bg-[#5ad2ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#36C7FF] sm:flex-1"
                    >Заявка</a>
                @else
                    @if ($book !== '')
                        <a
                            href="{{ e($book) }}"
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-[#36C7FF] px-4 text-center text-sm font-semibold text-carbon shadow-md shadow-black/20 transition hover:bg-[#5ad2ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#36C7FF]"
                        >Запись</a>
                    @endif
                    @if ($detail !== '')
                        <a
                            href="{{ e($detail) }}"
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-white/15 bg-white/[0.06] px-4 text-center text-sm font-medium text-white transition hover:border-[#36C7FF]/35 hover:bg-white/[0.09] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#36C7FF]"
                        >Подробнее</a>
                    @endif
                @endif
            </div>
        </div>
    </article>
</li>
