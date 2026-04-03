@props([
    'name' => '',
])

@php
    use App\PageBuilder\PageBuilderIconCatalog;

    $key = strtolower(trim((string) $name));
    $hero = $key !== '' ? PageBuilderIconCatalog::heroiconForKey($key) : null;
@endphp

@if ($hero)
    <span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-lg bg-white/10 text-white']) }} aria-hidden="true">
        {!! svg($hero, 'h-6 w-6 sm:h-7 sm:w-7', ['width' => 28, 'height' => 28])->toHtml() !!}
    </span>
@endif
