@php
    $d = is_array($data ?? null) ? $data : [];
    $items = is_array($d['items'] ?? null) ? $d['items'] : [];
    $heading = (string) ($d['heading'] ?? 'Кейсы');
@endphp
<section class="bd-section" aria-labelledby="bd-cases-heading">
    <h2 id="bd-cases-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
    @if (count($items) > 0)
        <ul class="mt-6 space-y-6" role="list">
            @foreach ($items as $it)
                @php
                    $caseImg = \App\Tenant\Expert\ExpertBrandMediaUrl::resolve($it['image_url'] ?? '');
                @endphp
                <li class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] sm:flex">
                    @if (filled($caseImg))
                        <div class="sm:w-1/3">
                            <img src="{{ $caseImg }}" alt="" class="h-48 w-full object-cover sm:h-full" loading="lazy" />
                        </div>
                    @endif
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
    @endif
</section>
