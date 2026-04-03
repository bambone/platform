@php
    use App\PageBuilder\LegacySectionTypeResolver;
    use App\Services\PageBuilder\SectionViewResolver;

    $legacy = app(LegacySectionTypeResolver::class);
    $resolver = app(SectionViewResolver::class);
    $key = (string) $section->section_key;
    $type = $legacy->effectiveTypeId($section);
    $data = is_array($section->data_json) ? $section->data_json : [];
@endphp

@if ($type === 'motorcycle_catalog')
    @include('tenant.partials.home-motorcycle-catalog', [
        'bikes' => $bikes,
        'badges' => $badges,
        'heading' => $data['heading'] ?? 'Наш автопарк',
        'subheading' => $data['subheading'] ?? null,
    ])
@elseif ($type === 'hero')
    <x-hero :section="$data" />
@elseif ($key === 'route_cards')
    <x-experience-block :section="$data" />
@elseif ($key === 'why_us')
    <x-why-us :section="$data" />
@elseif ($key === 'how_it_works')
    <x-how-it-works :section="$data" />
@elseif ($key === 'rental_conditions')
    <x-rental-conditions :section="$data" />
@elseif ($key === 'reviews_block')
    <x-social-proof :section="$data" :reviews="$reviews ?? []" />
@elseif ($key === 'faq_block')
    <x-faq-block :section="$data" :faqs="$faqs ?? []" />
@elseif ($key === 'final_cta')
    <x-final-cta :section="$data" />
@else
    @php
        $viewName = $resolver->resolveViewName($section);
    @endphp
    @if ($viewName !== null)
        @include($viewName, [
            'section' => $section,
            'data' => $data,
            'bikes' => $bikes ?? null,
            'badges' => $badges ?? [],
        ])
    @endif
@endif
