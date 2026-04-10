@props(['for' => null])
<p
    {{ $attributes->class(['rb-public-field-hint']) }}
    @if($for) id="{{ $for }}-hint" @endif
>{{ $slot }}</p>
