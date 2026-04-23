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
        $pageShell = in_array(tenant()?->themeKey(), ['expert_auto', 'advocate_editorial', 'black_duck'], true)
            ? 'max-w-[min(72rem,calc(100vw-1.5rem))] md:px-10'
            : 'max-w-4xl md:px-8';
    @endphp
    @php
        $firstExtra = $extraSections->first();
        $skipShellH1 = tenant()?->themeKey() === 'black_duck'
            && $firstExtra !== null
            && in_array($firstExtra->section_key, ['hero', 'works_hero'], true);
        $isBlackDuckHome = tenant()?->themeKey() === 'black_duck' && ($page->slug ?? '') === 'home';
    @endphp
    <div class="mx-auto px-3 pt-24 sm:px-4 sm:pt-28 {{ $isBlackDuckHome ? 'pb-6 sm:pb-8' : 'pb-12 sm:pb-16' }} {{ $pageShell }}">
        @if (tenant()?->themeKey() === 'black_duck' && request()->boolean('book'))
            @php
                $bdBookReg = \App\Tenant\BlackDuck\BlackDuckServiceRegistry::rowBySlug((string) $page->slug);
                $bdBookInquiry = $bdBookReg !== null
                    ? \App\Tenant\BlackDuck\BlackDuckContentConstants::contactsInquiryUrlForServiceSlug((string) $page->slug)
                    : \App\Tenant\BlackDuck\BlackDuckContentConstants::PRIMARY_LEAD_URL;
                $bdBookTitle = $bdBookReg !== null ? (string) ($bdBookReg['title'] ?? $page->name) : $page->name;
            @endphp
            @if ($bdBookReg !== null)
                <div class="bd-book-intent-banner mb-6 rounded-2xl border border-[#36C7FF]/25 bg-[#36C7FF]/10 px-4 py-4 sm:px-5 sm:py-5" role="status">
                    <p class="text-sm font-medium text-white sm:text-base">Запись на услугу: {{ $bdBookTitle }}</p>
                    <p class="mt-1 text-sm text-zinc-300">Форма на отдельной странице — услуга уже подставлена в текст обращения.</p>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <a href="{{ e($bdBookInquiry) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#36C7FF] px-5 text-sm font-semibold text-carbon hover:bg-[#5ad2ff]">Перейти к форме</a>
                        <a href="{{ e(url($page->slug)) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 px-5 text-sm font-medium text-zinc-200 hover:bg-white/5">Обычный просмотр страницы</a>
                    </div>
                </div>
            @endif
        @endif
        @unless ($skipShellH1)
            <h1 class="mb-6 text-balance text-2xl font-bold leading-tight text-white sm:mb-8 sm:text-3xl md:text-4xl">{{ ($resolvedSeo ?? null)?->h1 ?? $page->name }}</h1>
        @endunless

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
                    @include($viewName, [
                        'section' => $section,
                        'data' => $data,
                        'page' => $page,
                        'isFirstVisibleExtra' => $loop->first,
                    ])
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
