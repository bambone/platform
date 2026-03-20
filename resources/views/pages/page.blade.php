@php
    $seoMeta = $seoMeta ?? null;
    $sections = $page->sections()->where('status', 'published')->where('is_visible', true)->orderBy('sort_order')->get()->keyBy('section_key');
@endphp
<x-app-layout :meta="$seoMeta">
    <div class="max-w-4xl mx-auto px-4 md:px-8 py-20">
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-8">{{ $page->name }}</h1>
        <div class="prose prose-invert max-w-none text-silver">
            @foreach($sections as $section)
                @if(!empty($section->data_json))
                    @php $d = $section->data_json; @endphp
                    @if(isset($d['content']))
                        {!! $d['content'] !!}
                    @elseif(isset($d['heading']))
                        <h2>{{ $d['heading'] }}</h2>
                        @if(isset($d['content'])){!! $d['content'] !!}@endif
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</x-app-layout>
