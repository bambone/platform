@php
    $d = is_array($data ?? null) ? $data : [];
    $items = is_array($d['items'] ?? null) ? $d['items'] : [];
    $heading = (string) ($d['heading'] ?? 'Услуги');
@endphp
<section class="bd-section bd-service-hub" aria-labelledby="bd-hub-heading">
    <h2 id="bd-hub-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
    @if (count($items) > 0)
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
            @foreach ($items as $it)
                @php
                    $img = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($it['image_url'] ?? '');
                    $title = (string) ($it['title'] ?? '');
                    $imgAlt = $title !== '' ? 'Фото: '.$title : 'Услуга';
                    $sub = trim((string) ($it['card_subtitle'] ?? ''));
                    $cta = trim((string) ($it['cta_url'] ?? ''));
                    $mode = (string) ($it['booking_mode'] ?? '');
                @endphp
                <li class="h-full min-h-0">
                    @if ($cta !== '')
                        <a href="{{ $cta }}" class="group flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] p-0 text-left shadow-sm shadow-black/20 transition duration-300 hover:-translate-y-0.5 hover:border-[#36C7FF]/25 hover:shadow-md hover:shadow-black/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#36C7FF]">
                    @else
                        <div class="group flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] p-0 shadow-sm shadow-black/20">
                    @endif
                    @if (filled($img))
                        <div class="relative aspect-[16/9] w-full shrink-0 overflow-hidden">
                            <img
                                src="{{ $img }}"
                                alt="{{ $imgAlt }}"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            />
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-transparent" aria-hidden="true"></div>
                        </div>
                    @endif
                    <div class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                        <p class="font-medium leading-snug text-zinc-100">{{ $title }}</p>
                        @if ($sub !== '')
                            <p class="mt-1.5 line-clamp-2 text-sm leading-snug text-zinc-400">{{ $sub }}</p>
                        @endif
                        <p class="mt-2 text-sm text-zinc-500">{{ (string) ($it['price_from'] ?? '') }}@if(!empty($it['duration'])) · {{ (string) ($it['duration'] ?? '') }}@endif</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @if ($mode === 'instant')
                                <span class="rounded bg-[#F0FF00]/15 px-2 py-0.5 text-[#F0FF00]">быстрый слот</span>
                            @endif
                            @if ($mode === 'quote')
                                <span class="rounded bg-violet-500/20 px-2 py-0.5 text-violet-200">расчёт</span>
                            @endif
                            @if ($mode === 'confirm')
                                <span class="rounded bg-white/10 px-2 py-0.5 text-zinc-300">по записи</span>
                            @endif
                            @if (!empty($it['online_booking']))<span class="rounded bg-[#F0FF00]/15 px-2 py-0.5 text-[#F0FF00]">онлайн</span>@endif
                            @if (!empty($it['needs_confirmation']))<span class="rounded bg-white/10 px-2 py-0.5 text-zinc-300">согласование</span>@endif
                        </div>
                        @if ($cta !== '')
                            <span class="mt-4 text-sm font-medium text-[#36C7FF] underline-offset-2 group-hover:underline">Подробнее</span>
                        @endif
                    </div>
                    @if ($cta !== '')
                        </a>
                    @else
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
