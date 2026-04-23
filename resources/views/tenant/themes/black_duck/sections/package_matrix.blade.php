@php
    $d = is_array($data ?? null) ? $data : [];
    $cols = is_array($d['columns'] ?? null) ? $d['columns'] : [];
    $heading = (string) ($d['heading'] ?? 'Пакеты');
@endphp
<section class="bd-section" aria-labelledby="bd-pm-heading">
    <h2 id="bd-pm-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
    @if (count($cols) > 0)
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
            @foreach ($cols as $c)
                <div class="rounded-2xl border border-white/10 p-4" role="listitem">
                    <p class="text-lg font-semibold text-zinc-100">{{ (string) ($c['name'] ?? '') }}</p>
                    <p class="mt-1 text-sm text-zinc-400">{{ (string) ($c['price_hint'] ?? '') }}</p>
                </div>
            @endforeach
        </div>
    @endif
</section>
