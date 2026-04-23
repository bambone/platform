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
                @endphp
                <li class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] p-0">
                    @if (filled($img))
                        <div class="aspect-[16/9] w-full overflow-hidden">
                            <img src="{{ $img }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                        </div>
                    @endif
                    <div class="p-4">
                    <p class="font-medium text-zinc-100">{{ (string) ($it['title'] ?? '') }}</p>
                    <p class="mt-1 text-sm text-zinc-400">{{ (string) ($it['price_from'] ?? '') }} @if(!empty($it['duration']))· {{ (string) $it['duration'] }}@endif</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        @if (!empty($it['online_booking']))<span class="rounded bg-[#F0FF00]/15 px-2 py-0.5 text-[#F0FF00]">онлайн</span>@endif
                        @if (!empty($it['needs_confirmation']))<span class="rounded bg-white/10 px-2 py-0.5 text-zinc-300">по подтверждению</span>@endif
                    </div>
                    @if (filled($it['cta_url'] ?? null))
                        <a href="{{ (string) $it['cta_url'] }}" class="mt-3 inline-block text-sm font-medium text-[#36C7FF] underline-offset-2 hover:underline">Подробнее</a>
                    @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
