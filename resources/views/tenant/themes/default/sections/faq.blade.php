@php
    $h = $data['section_heading'] ?? ($data['heading'] ?? '');
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if ($items === [] && tenant() && ($data['source'] ?? null) === 'faqs_table') {
        $items = \App\Models\Faq::query()
            ->where('show_on_home', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($faq) => ['question' => $faq->question, 'answer' => $faq->answer])
            ->all();
    }
@endphp
@if(count($items) > 0)
<section>
    @if(filled($h))
        <h2 class="mb-6 text-balance text-xl font-bold text-white sm:text-2xl">{{ $h }}</h2>
    @endif
    <dl class="space-y-4">
        @foreach($items as $item)
            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                <dt class="font-semibold text-white">{{ $item['question'] ?? '' }}</dt>
                <dd class="mt-2 text-sm text-silver">{!! nl2br(e($item['answer'] ?? '')) !!}</dd>
            </div>
        @endforeach
    </dl>
</section>
@endif
