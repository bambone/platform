@php
    $h = $data['section_heading'] ?? '';
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
@endphp
<section>
    @if(filled($h))
        <h2 class="mb-6 text-balance text-xl font-bold text-white sm:text-2xl">{{ $h }}</h2>
    @endif
    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach($items as $item)
            <li class="rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="flex items-start gap-3">
                    @php
                        $iconName = (string) ($item['icon'] ?? '');
                    @endphp
                    @if(\App\PageBuilder\PageBuilderIconCatalog::heroiconForKey($iconName))
                        <x-tenant.page-section-icon :name="$iconName" class="mt-0.5 h-9 w-9" />
                    @else
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-xs font-bold uppercase text-white/90" aria-hidden="true">{{ strtoupper(substr($iconName !== '' ? $iconName : '?', 0, 1)) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-white">{{ $item['title'] ?? '' }}</h3>
                        @if(filled($item['description'] ?? ''))
                            <p class="mt-2 text-sm text-silver">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</section>
