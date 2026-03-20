@props(['meta' => null])
@php
    $m = $meta;
    $title = $m?->meta_title ?? config('app.name', 'Moto Levins');
    $description = $m?->meta_description ?? null;
    $keywords = $m?->meta_keywords ?? null;
    $canonical = $m?->canonical_url ?? null;
    $robots = $m?->robots ?? null;
    $isIndexable = $m?->is_indexable ?? true;
    $isFollowable = $m?->is_followable ?? true;
    $ogTitle = $m?->og_title ?? $title;
    $ogDescription = $m?->og_description ?? $description;
    $ogImage = $m?->og_image ?? null;
    $ogType = $m?->og_type ?? 'website';
    $twitterCard = $m?->twitter_card ?? 'summary_large_image';
@endphp
<title>{{ $title }}</title>
@if($description)
<meta name="description" content="{{ $description }}">
@endif
@if($keywords)
<meta name="keywords" content="{{ $keywords }}">
@endif
@if($canonical)
<link rel="canonical" href="{{ $canonical }}">
@endif
@if($robots || !$isIndexable || !$isFollowable)
<meta name="robots" content="{{ $robots ?? ($isIndexable ? 'index' : 'noindex') . ', ' . ($isFollowable ? 'follow' : 'nofollow') }}">
@endif
<meta property="og:title" content="{{ $ogTitle }}">
@if($ogDescription)
<meta property="og:description" content="{{ $ogDescription }}">
@endif
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
@if($ogDescription)
<meta name="twitter:description" content="{{ $ogDescription }}">
@endif
@if($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
@endif
