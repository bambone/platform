@php
    use App\PageBuilder\LegacySectionTypeResolver;
    use App\Support\PageRichContent;
    use App\Services\PageBuilder\SectionViewResolver;

    $seoMeta = $seoMeta ?? null;
    $sectionResolver = app(SectionViewResolver::class);
    $typeResolver = app(LegacySectionTypeResolver::class);
    $sections = $page->sections()
        ->where('status', 'published')
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
    $mainSection = $sections->firstWhere('section_key', 'main');
    $extraSections = $sections->filter(fn ($s) => $s->section_key !== 'main');

    $termsNav = [];
    if ($page->slug === 'usloviya-arenda') {
        foreach ($extraSections as $s) {
            $tid = $typeResolver->effectiveTypeId($s);
            if (! in_array($tid, ['structured_text', 'text_section'], true)) {
                continue;
            }
            $d = is_array($s->data_json) ? $s->data_json : [];
            $label = trim((string) ($d['title'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($s->title ?? ''));
            }
            if ($label === '') {
                continue;
            }
            $termsNav['section-'.$s->id] = $label;
        }
    }
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="w-full min-w-0 bg-carbon pb-16 sm:pb-20 md:pb-24">
        <div class="mx-auto max-w-6xl px-3 pt-24 sm:px-4 sm:pt-28 md:px-8 xl:max-w-7xl xl:px-10">
            <header class="mb-8 max-w-3xl sm:mb-10 md:mb-12 @if($page->slug === 'contacts') relative @endif">
                <h1 class="text-balance text-3xl font-bold leading-[1.12] tracking-tight text-white sm:text-4xl md:text-5xl md:font-semibold md:tracking-tight">
                    {{ $page->name }}
                </h1>
                @if($page->slug === 'contacts')
                    <div class="pointer-events-none mt-5 h-px w-full max-w-md bg-gradient-to-r from-moto-amber/50 via-moto-amber/20 to-transparent sm:mt-6" aria-hidden="true"></div>
                @endif
            </header>

            @if($mainSection && is_array($mainSection->data_json) && filled($mainSection->data_json['content'] ?? null))
                @if($page->slug === 'contacts')
                    <div class="mb-10 max-w-2xl text-lg leading-relaxed text-white/[0.88] sm:mb-12 sm:text-xl sm:leading-relaxed [&_a]:inline-flex [&_a]:min-h-11 [&_a]:items-center [&_a]:rounded-lg [&_a]:bg-moto-amber/15 [&_a]:px-4 [&_a]:py-2 [&_a]:text-base [&_a]:font-semibold [&_a]:text-moto-amber [&_a]:no-underline [&_a]:ring-1 [&_a]:ring-inset [&_a]:ring-moto-amber/35 [&_a]:transition-colors hover:[&_a]:bg-moto-amber/25 hover:[&_a]:text-amber-200 focus-visible:[&_a]:outline focus-visible:[&_a]:ring-2 focus-visible:[&_a]:ring-moto-amber/60">
                        {!! PageRichContent::toHtml($mainSection->data_json['content']) !!}
                    </div>
                @elseif($page->slug === 'usloviya-arenda')
                    <x-tenant.rich-prose variant="intro" class="mb-12 sm:mb-14 lg:mb-16" :content="$mainSection->data_json['content']" />
                @else
                    <x-tenant.rich-prose variant="simple" class="mb-8 sm:mb-10" :content="$mainSection->data_json['content']" />
                @endif
            @endif

            @if($page->slug === 'usloviya-arenda' && count($termsNav) > 0)
                <div class="border-t border-white/5 pt-12 lg:pt-14">
                    <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-14 xl:gap-20">
                        <x-custom-pages.terms.sticky-sidebar-nav :sections="$termsNav" />
                        <div class="min-w-0 flex-1 space-y-12 md:space-y-14 lg:space-y-16 lg:pb-8">
                            @foreach($extraSections as $section)
                                @php
                                    $data = is_array($section->data_json) ? $section->data_json : [];
                                    $viewName = $sectionResolver->resolveViewName($section);
                                @endphp
                                @if($viewName !== null)
                                    @include($viewName, ['section' => $section, 'data' => $data, 'page' => $page])
                                @elseif(filled($data['content'] ?? null) || filled($data['heading'] ?? null))
                                    @if(! empty($data['content']))
                                        <x-tenant.rich-prose variant="simple" class="max-w-none" :content="$data['content']" />
                                    @elseif(! empty($data['heading']))
                                        <div class="prose prose-invert max-w-none text-sm text-silver prose-headings:text-white sm:text-base">
                                            <h2>{{ $data['heading'] }}</h2>
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-col gap-8 sm:gap-10">
                    @foreach($extraSections as $section)
                        @php
                            $data = is_array($section->data_json) ? $section->data_json : [];
                            $viewName = $sectionResolver->resolveViewName($section);
                        @endphp
                        @if($viewName !== null)
                            @include($viewName, ['section' => $section, 'data' => $data, 'page' => $page])
                        @elseif(filled($data['content'] ?? null) || filled($data['heading'] ?? null))
                            @if(! empty($data['content']))
                                <x-tenant.rich-prose variant="simple" class="max-w-none" :content="$data['content']" />
                            @elseif(! empty($data['heading']))
                                <div class="prose prose-invert max-w-none text-sm text-silver prose-headings:text-white sm:text-base">
                                    <h2>{{ $data['heading'] }}</h2>
                                </div>
                            @endif
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
