@props([
    'as' => 'span',
    'leftPercent',
    'widthPercent' => null,
])

@php
    $styleString = 'left: '.e((string) $leftPercent).'%';
    if ($widthPercent !== null) {
        $styleString .= '; width: '.e((string) $widthPercent).'%';
    }
@endphp

<{{ $as }} {{ $attributes->merge(['style' => $styleString]) }}>{{ $slot }}</{{ $as }}>
