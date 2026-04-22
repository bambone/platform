@php
    $previewEngine = (string) ($previewEngine ?? 'simulated');
@endphp

{{-- JS: resources/js/service-program-cover-focal-editor.js в AdminPanelProvider (HEAD_END). --}}

<x-dynamic-component :component="$field->getFieldWrapperView()" :field="$field">
    @if($previewEngine === 'public_card')
        @include('filament.forms.components._service-program-cover-preview-public-card')
    @else
        @include('filament.forms.components._service-program-cover-preview-simulated')
    @endif
</x-dynamic-component>
