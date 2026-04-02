@props([
    'field' => null,
    'id' => null,
    'label' => null,
    'labelTag' => 'label',
])

@php
    use Illuminate\View\ComponentAttributeBag;

    if ($field) {
        $id ??= $field->getId();
        $label ??= $field->getLabel();
    }

    $omitLabelForAttribute = $field instanceof \Filament\Forms\Components\Select
        && ($field->isSearchable() || $field->isMultiple() || ! $field->isNative());
@endphp

<div
    data-field-wrapper
    {{
        (new ComponentAttributeBag)
            ->merge($field?->getExtraFieldWrapperAttributes() ?? [], escape: false)
            ->class([
                'fi-fo-field',
            ])
    }}
>
    @if (filled($label))
        <{{ $labelTag }}
            @if ($labelTag === 'label')
                @if ($omitLabelForAttribute ?? false)
                    id="{{ $id }}-label"
                @else
                    for="{{ $id }}"
                @endif
            @else
                id="{{ $id }}-label"
            @endif
            class="fi-fo-field-label fi-sr-only"
        >
            {{ $label }}
        </{{ $labelTag }}>
    @endif

    {{ $slot }}
</div>
