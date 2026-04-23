@php
    $d = is_array($data ?? null) ? $data : [];
    $items = is_array($d['items'] ?? null) ? $d['items'] : [];
    $heading = (string) ($d['heading'] ?? 'Кейсы');
    $visualRows = [];
    foreach ($items as $it) {
        if (! is_array($it)) {
            continue;
        }
        $caseImg = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($it['image_url'] ?? '');
        if (filled($caseImg)) {
            $visualRows[] = [$it, $caseImg];
        }
    }
    $workLabel = trim((string) ($d['proof_works_cta_label'] ?? ''));
    $workHref = trim((string) ($d['proof_works_cta_href'] ?? ''));
@endphp
@if (count($visualRows) < 1)
@else
<section class="bd-section" aria-labelledby="bd-cases-heading">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <h2 id="bd-cases-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
        @if ($workLabel !== '' && $workHref !== '')
            <a href="{{ e($workHref) }}" class="shrink-0 text-sm font-medium text-[#36C7FF] underline-offset-2 hover:underline">{{ $workLabel }}</a>
        @endif
    </div>
    <ul class="mt-6 space-y-6" role="list">
        @foreach ($visualRows as [$it, $caseImg])
            <li class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] sm:flex">
                <div class="sm:w-1/3">
                    <img src="{{ $caseImg }}" alt="" class="h-48 w-full object-cover sm:h-full" loading="lazy" decoding="async" />
                </div>
                <div class="flex-1 p-4">
                    <p class="text-sm text-zinc-500">{{ (string) ($it['vehicle'] ?? '') }}</p>
                    <p class="mt-1 font-medium text-zinc-100">{{ (string) ($it['task'] ?? '') }}</p>
                    @if (filled($it['duration'] ?? null))
                        <p class="mt-2 text-sm text-zinc-400">Срок: {{ (string) $it['duration'] }}</p>
                    @endif
                    @if (filled($it['result'] ?? null))
                        <p class="mt-2 text-sm text-zinc-300">Итог: {{ (string) $it['result'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endif
