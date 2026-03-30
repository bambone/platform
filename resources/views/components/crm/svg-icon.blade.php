@props([
    'name',
    'size' => 'sm',
])

@php
    $size = in_array($size, ['xs', 'sm', 'md', 'lg'], true) ? $size : 'sm';
    $px = match ($size) {
        'xs' => 12,
        'sm' => 14,
        'md' => 16,
        'lg' => 32,
    };
    $classForSvg = trim((string) ($attributes->get('class') ?? ''));
    $svgAttrs = array_merge(
        $attributes->except(['class'])->getAttributes(),
        ['width' => (string) $px, 'height' => (string) $px]
    );
@endphp

<span @class(['crm-svg-icon-host', 'crm-svg-icon-host--'.$size]) aria-hidden="true">
    {!! svg($name, $classForSvg, $svgAttrs)->toHtml() !!}
</span>
