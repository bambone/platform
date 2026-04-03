@php
    $title = $data['title'] ?? '';
    $cols = (int) ($data['columns'] ?? 3);
    $cols = in_array($cols, [2, 3, 4], true) ? $cols : 3;
    $gridClass = match ($cols) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
@endphp
<section class="w-full min-w-0" data-page-section-type="{{ $section->section_type }}">
    @if(filled($title))
        <h2 class="mb-6 text-balance text-xl font-semibold text-white sm:text-2xl">{{ $title }}</h2>
    @endif
    @if($items !== [])
        <ul class="grid grid-cols-1 gap-4 {{ $gridClass }}">
            @foreach($items as $item)
                @php
                    $it = is_array($item) ? $item : [];
                    $icon = $it['icon'] ?? 'info';
                    $t = $it['title'] ?? '';
                    $tx = $it['text'] ?? '';
                @endphp
                <li class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="mb-2 flex items-start gap-3">
                        @if(\App\PageBuilder\PageBuilderIconCatalog::heroiconForKey((string) $icon))
                            <x-tenant.page-section-icon :name="$icon" class="h-9 w-9 flex-shrink-0" />
                        @else
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-white/10 text-xs font-bold uppercase text-white/90" aria-hidden="true">{{ strtoupper(substr((string) $icon, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            @if(filled($t))
                                <h3 class="text-base font-semibold text-white">{{ $t }}</h3>
                            @endif
                            @if(filled($tx))
                                <p class="mt-1 text-sm leading-relaxed text-silver">{{ $tx }}</p>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
