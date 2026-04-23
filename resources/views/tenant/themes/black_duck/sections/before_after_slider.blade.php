@php
    $d = is_array($data ?? null) ? $data : [];
    $pairs = is_array($d['pairs'] ?? null) ? $d['pairs'] : [];
    $heading = (string) ($d['heading'] ?? 'До / после');
@endphp
<section class="bd-section" aria-labelledby="bd-ba-heading">
    <h2 id="bd-ba-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
    @if (count($pairs) > 0)
        <div class="mt-6 space-y-8">
            @foreach ($pairs as $p)
                @php
                    $baBefore = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($p['before_url'] ?? '');
                    $baAfter = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($p['after_url'] ?? '');
                @endphp
                <figure class="grid gap-4 sm:grid-cols-2">
                    @if (filled($baBefore))
                        <div class="overflow-hidden rounded-xl border border-white/10">
                            <img src="{{ $baBefore }}" alt="До" class="h-auto w-full object-cover" loading="lazy" />
                        </div>
                    @endif
                    @if (filled($baAfter))
                        <div class="overflow-hidden rounded-xl border border-white/10">
                            <img src="{{ $baAfter }}" alt="После" class="h-auto w-full object-cover" loading="lazy" />
                        </div>
                    @endif
                    @if (filled($p['caption'] ?? null))
                        <figcaption class="sm:col-span-2 text-sm text-zinc-400">{{ (string) $p['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    @endif
</section>
