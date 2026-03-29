@php
    $seoMeta = $seoMeta ?? null;
    $sections = $page->sections()->where('status', 'published')->where('is_visible', true)->orderBy('sort_order')->get()->keyBy('section_key');
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
        <h1 class="mb-6 text-balance text-2xl font-bold leading-tight text-white sm:mb-8 sm:text-3xl md:text-4xl">{{ $page->name }}</h1>
        <div class="prose prose-invert max-w-none text-sm text-silver prose-headings:text-white prose-p:leading-relaxed sm:text-base">
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
@endsection
