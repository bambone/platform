@php
    use App\Tenant\BlackDuck\BlackDuckProofDisplay;
    use App\Tenant\Expert\ExpertBrandMediaUrl;

    $d = is_array($data ?? null) ? $data : [];
    $items = is_array($d['items'] ?? null) ? $d['items'] : [];
    $heading = (string) ($d['heading'] ?? 'Кейсы');
    $compactGallery = isset($section) && ($section->section_key ?? '') === 'service_proof';
    $visualItems = [];
    foreach ($items as $it) {
        if (! is_array($it)) {
            continue;
        }
        $path = trim((string) ($it['image_url'] ?? ''));
        if ($path === '' || ! filled(ExpertBrandMediaUrl::resolve($path))) {
            continue;
        }
        $visualItems[] = $it;
    }
    $workLabel = trim((string) ($d['proof_works_cta_label'] ?? ''));
    $workHref = trim((string) ($d['proof_works_cta_href'] ?? ''));
    $gridGap = $compactGallery
        ? 'mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3'
        : 'mt-6 space-y-6';
@endphp
@if (count($visualItems) < 1)
@else
<section class="bd-section" aria-labelledby="bd-cases-heading">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <h2 id="bd-cases-heading" class="text-2xl font-semibold text-[var(--ex-ink)]">{{ $heading }}</h2>
        @if ($workLabel !== '' && $workHref !== '')
            <a href="{{ e($workHref) }}" class="shrink-0 text-sm font-medium text-[#36C7FF] underline-offset-2 hover:underline">{{ $workLabel }}</a>
        @endif
    </div>
    <ul class="{{ $gridGap }}" role="list">
        @foreach ($visualItems as $it)
            @php
                $path = trim((string) ($it['image_url'] ?? ''));
                $altT = BlackDuckProofDisplay::altForItem($it);
                $srcset = trim((string) ($it['srcset'] ?? ''));
                $sizes = trim((string) ($it['sizes'] ?? ''));
                $ar = isset($it['aspect_ratio']) && is_string($it['aspect_ratio']) && $it['aspect_ratio'] !== ''
                    ? $it['aspect_ratio']
                    : '4 / 3';
                $title = trim((string) ($it['title'] ?? ''));
                $cap = trim((string) ($it['caption'] ?? $it['task'] ?? ''));
                $sum = trim((string) ($it['summary'] ?? ''));
            @endphp
            @if ($compactGallery)
                <li class="flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]">
                    @include('tenant.themes.black_duck.components.proof_picture', [
                        'logicalPath' => $path,
                        'srcset' => $srcset,
                        'sizes' => $sizes,
                        'alt' => $altT,
                        'aspectRatio' => $ar,
                        'class' => '',
                        'loading' => 'lazy',
                        'fetchpriority' => null,
                    ])
                    @if ($title !== '' || $cap !== '' || $sum !== '')
                        <div class="space-y-1 px-3 py-3">
                            @if ($title !== '')
                                <p class="text-sm font-medium text-zinc-100">{{ e($title) }}</p>
                            @endif
                            @if ($cap !== '' && $cap !== $title)
                                <p class="text-xs text-zinc-400">{{ e($cap) }}</p>
                            @endif
                            @if ($sum !== '')
                                <p class="text-xs leading-relaxed text-zinc-500">{{ e($sum) }}</p>
                            @endif
                        </div>
                    @endif
                </li>
            @else
                <li class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] sm:flex">
                    <div class="sm:w-2/5 sm:max-w-md">
                        @include('tenant.themes.black_duck.components.proof_picture', [
                            'logicalPath' => $path,
                            'srcset' => $srcset,
                            'sizes' => $sizes ?: '(max-width: 640px) 100vw, 40vw',
                            'alt' => $altT,
                            'aspectRatio' => $ar,
                            'class' => 'max-h-64 sm:max-h-none sm:min-h-[12rem]',
                            'loading' => 'lazy',
                            'fetchpriority' => null,
                        ])
                    </div>
                    <div class="flex-1 p-4">
                        <p class="text-sm text-zinc-500">{{ (string) ($it['vehicle'] ?? '') }}</p>
                        @if ($title !== '')
                            <p class="mt-1 font-medium text-zinc-100">{{ e($title) }}</p>
                        @else
                            <p class="mt-1 font-medium text-zinc-100">{{ (string) ($it['task'] ?? '') }}</p>
                        @endif
                        @if ($sum !== '')
                            <p class="mt-2 text-sm text-zinc-400">{{ e($sum) }}</p>
                        @endif
                        @if (filled($it['duration'] ?? null))
                            <p class="mt-2 text-sm text-zinc-400">Срок: {{ (string) $it['duration'] }}</p>
                        @endif
                        @if (filled($it['result'] ?? null))
                            <p class="mt-2 text-sm text-zinc-300">Итог: {{ (string) $it['result'] }}</p>
                        @endif
                    </div>
                </li>
            @endif
        @endforeach
    </ul>
</section>
@endif
