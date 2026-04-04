@php
    /** @var \App\Services\Seo\SeoResolvedData|null $r */
    $r = $resolvedSeo ?? null;
    $fallbackTitle = trim($site_name ?? '') !== '' ? $site_name : 'Rent';
@endphp
@if($r instanceof \App\Services\Seo\SeoResolvedData)
<title>{{ $r->title }}</title>
@if($r->description !== '')
<meta name="description" content="{{ $r->description }}">
@endif
@if($r->metaKeywords)
<meta name="keywords" content="{{ $r->metaKeywords }}">
@endif
<link rel="canonical" href="{{ $r->canonical }}">
<meta name="robots" content="{{ $r->robots }}">
<meta property="og:title" content="{{ $r->ogTitle }}">
@if($r->ogDescription !== '')
<meta property="og:description" content="{{ $r->ogDescription }}">
@endif
@if($r->ogImage)
<meta property="og:image" content="{{ $r->ogImage }}">
@endif
<meta property="og:type" content="{{ $r->ogType }}">
<meta property="og:site_name" content="{{ $r->ogSiteName }}">
<meta property="og:url" content="{{ $r->ogUrl }}">
<meta name="twitter:card" content="{{ $r->twitterCard }}">
<meta name="twitter:title" content="{{ $r->ogTitle }}">
@if($r->ogDescription !== '')
<meta name="twitter:description" content="{{ $r->ogDescription }}">
@endif
@if($r->ogImage)
<meta name="twitter:image" content="{{ $r->ogImage }}">
@endif
@if($r->jsonLd !== [])
@push('tenant-jsonld')
@php
    // Blade иначе воспринимает @context / @graph как директивы — собираем ключи без «сырого» @ в разметке.
    $tenantJsonLdRoot = [
        '@'.'context' => 'https://schema.org',
        '@'.'graph' => $r->jsonLd,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($tenantJsonLdRoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@endif
@else
<title>{{ $fallbackTitle }}</title>
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:title" content="{{ $fallbackTitle }}">
<meta property="og:site_name" content="{{ $fallbackTitle }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fallbackTitle }}">
@endif
