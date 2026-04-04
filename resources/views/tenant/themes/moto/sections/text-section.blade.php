@php
    $title = isset($data['title']) ? trim((string) $data['title']) : '';
    $content = $data['content'] ?? '';
    $displayTitle = $title !== '' ? $title : trim((string) ($section->title ?? ''));
    if ($displayTitle === '') {
        $displayTitle = 'Раздел';
    }
    $iconSvg = '<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>';
@endphp
@if(! filled($content))
@else
<div class="mx-auto w-full min-w-0 max-w-none">
    <x-custom-pages.terms.policy-section-card
        id="section-{{ $section->id }}"
        :title="$displayTitle"
        :icon="$iconSvg"
        :content="$content"
    />
</div>
@endif
