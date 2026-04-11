@php
    use App\Services\PageBuilder\SectionViewResolver;

    $sectionResolver = app(SectionViewResolver::class);
    $sections = $page->sections()
        ->where('status', 'published')
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
    $mainSection = $sections->firstWhere('section_key', 'main');
    $extraSections = $sections->filter(fn ($s) => $s->section_key !== 'main');
@endphp
@extends('tenant.layouts.app')

@section('content')
    @php
        $pageShell = tenant()?->themeKey() === 'expert_auto'
            ? 'max-w-[min(72rem,calc(100vw-1.5rem))] md:px-10'
            : 'max-w-4xl md:px-8';
    @endphp
    <div class="mx-auto px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 {{ $pageShell }}">
        <h1 class="mb-6 text-balance text-2xl font-bold leading-tight text-white sm:mb-8 sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? $page->name }}</h1>

        @if($mainSection && is_array($mainSection->data_json) && filled($mainSection->data_json['content'] ?? null))
            <x-tenant.rich-prose variant="default" class="mb-12" :content="$mainSection->data_json['content']" />
        @endif

        <div class="flex flex-col gap-12">
            @foreach($extraSections as $section)
                @php
                    $data = is_array($section->data_json) ? $section->data_json : [];
                    $viewName = $sectionResolver->resolveViewName($section);
                @endphp
                @if($viewName !== null)
                    @include($viewName, ['section' => $section, 'data' => $data, 'page' => $page])
                @else
                    @if($data !== [])
                        @if(! empty($data['content']))
                            <x-tenant.rich-prose variant="default" :content="$data['content']" />
                        @elseif(! empty($data['heading']))
                            <div class="prose prose-invert max-w-none text-sm text-silver prose-headings:text-white prose-p:leading-relaxed sm:text-base">
                                <h2>{{ $data['heading'] }}</h2>
                            </div>
                        @endif
                    @endif
                @endif
            @endforeach
        </div>
    </div>
@endsection
