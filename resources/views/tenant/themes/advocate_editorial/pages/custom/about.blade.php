@php
    use App\Services\PageBuilder\SectionViewResolver;

    $sectionResolver = app(SectionViewResolver::class);
    $sections = $page->sections()
        ->where('status', 'published')
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
@endphp
@extends('tenant.layouts.app')

@section('content')
    @php
        $pageShell = 'max-w-[min(72rem,calc(100vw-1.5rem))] md:px-10';
        $h1 = ($resolvedSeo ?? null)?->h1 ?? $page->name;
    @endphp
    <div class="advocate-about-page mx-auto px-3 pb-12 pt-24 sm:px-4 sm:pb-20 sm:pt-28 {{ $pageShell }}">
        <header class="relative mb-12 overflow-hidden rounded-2xl border border-[rgb(28_31_38)]/[0.08] bg-gradient-to-br from-[#fdfcfa] via-[#f7f4ee] to-[#ebe6dc] px-5 py-8 shadow-[0_20px_50px_-28px_rgba(28,31,38,0.35)] sm:mb-16 sm:rounded-[1.75rem] sm:px-8 sm:py-10 md:px-12 md:py-12">
            <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-[#9a7b4f]/[0.12] blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-10 h-48 w-48 rounded-full bg-[#9a7b4f]/[0.08] blur-3xl" aria-hidden="true"></div>
            <p class="relative text-[0.65rem] font-bold uppercase tracking-[0.22em] text-[rgb(95_72_42)] sm:text-xs">Профиль</p>
            <h1 class="relative mt-2 max-w-3xl text-balance text-3xl font-extrabold leading-[1.12] tracking-tight text-[rgb(24_27_32)] sm:text-4xl md:text-[2.65rem] md:leading-[1.08]">{{ $h1 }}</h1>
            <p class="relative mt-4 max-w-2xl text-pretty text-base leading-relaxed text-[rgb(82_88_99)] sm:text-lg">
                {{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord('Реквизиты, направления практики, образование и проекты — в одном месте. Ниже — структурированная информация и возможность связаться по делу.') }}
            </p>
        </header>

        <div class="flex flex-col gap-14 sm:gap-16 lg:gap-20">
            @foreach($sections as $section)
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
