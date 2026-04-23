@php
    $d = is_array($data ?? null) ? $data : [];
    $pairs = is_array($d['pairs'] ?? null) ? $d['pairs'] : [];
    $heading = (string) ($d['heading'] ?? 'До / после');
    $usable = 0;
    foreach ($pairs as $p) {
        if (! is_array($p)) {
            continue;
        }
        $baBefore = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($p['before_url'] ?? '');
        $baAfter = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($p['after_url'] ?? '');
        if (filled($baBefore) && filled($baAfter)) {
            $usable++;
        }
    }
    $workLabel = trim((string) ($d['proof_works_cta_label'] ?? ''));
    $workHref = trim((string) ($d['proof_works_cta_href'] ?? ''));
    $leadLabel = trim((string) ($d['proof_lead_label'] ?? ''));
    $leadHref = trim((string) ($d['proof_lead_href'] ?? ''));
@endphp
@if ($usable >= 1)
<section class="bd-section" aria-labelledby="bd-ba-heading">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <h2 id="bd-ba-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
            @if ($leadLabel !== '' && $leadHref !== '')
                <a href="{{ e($leadHref) }}" class="shrink-0 text-sm font-medium text-[#36C7FF] underline-offset-2 hover:underline">{{ $leadLabel }}</a>
            @endif
            @if ($workLabel !== '' && $workHref !== '')
                <a href="{{ e($workHref) }}" class="shrink-0 text-sm font-medium text-zinc-300 underline-offset-2 hover:underline">{{ $workLabel }}</a>
            @endif
        </div>
    </div>
    <div class="mt-6 space-y-8">
        @foreach ($pairs as $p)
            @if (! is_array($p))
                @continue
            @endif
            @php
                $baBefore = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($p['before_url'] ?? '');
                $baAfter = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($p['after_url'] ?? '');
            @endphp
            @if (filled($baBefore) && filled($baAfter))
                <figure class="grid gap-4 sm:grid-cols-2">
                    <div class="overflow-hidden rounded-xl border border-white/10">
                        <img src="{{ $baBefore }}" alt="До" class="h-auto w-full object-cover" loading="lazy" decoding="async" />
                    </div>
                    <div class="overflow-hidden rounded-xl border border-white/10">
                        <img src="{{ $baAfter }}" alt="После" class="h-auto w-full object-cover" loading="lazy" decoding="async" />
                    </div>
                    @if (filled($p['caption'] ?? null))
                        <figcaption class="sm:col-span-2 text-sm text-zinc-400">{{ (string) $p['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
        @endforeach
    </div>
</section>
@endif
