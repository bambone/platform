@php
    $title = $data['title'] ?? '';
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
@endphp
<section class="w-full min-w-0" data-page-section-type="{{ $section->section_type }}">
    @if(filled($title))
        <h2 class="mb-6 text-balance text-xl font-semibold text-white sm:text-2xl">{{ $title }}</h2>
    @endif
    @if($items !== [])
        <div class="flex flex-col gap-2">
            @foreach($items as $idx => $item)
                @php
                    $q = is_array($item) ? ($item['question'] ?? '') : '';
                    $a = is_array($item) ? ($item['answer'] ?? '') : '';
                @endphp
                @if(filled($q))
                    <details class="group rounded-lg border border-white/10 bg-white/[0.03] px-4 py-3 open:bg-white/[0.05]" @if($idx === 0) open @endif>
                        <summary class="cursor-pointer list-none text-sm font-medium text-white outline-none [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center justify-between gap-2">
                                <span>{{ $q }}</span>
                                <span class="text-silver/60 transition group-open:rotate-180" aria-hidden="true">▼</span>
                            </span>
                        </summary>
                        @if(filled($a))
                            <x-tenant.rich-prose variant="default" class="mt-3 border-t border-white/10 pt-3 text-silver" :content="$a" />
                        @endif
                    </details>
                @endif
            @endforeach
        </div>
    @endif
</section>
