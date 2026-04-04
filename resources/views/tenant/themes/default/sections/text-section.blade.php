@php
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
@endphp
<section class="w-full min-w-0 border-t border-white/10 pt-10 first:border-t-0 first:pt-0" data-page-section-type="{{ $section->section_type }}">
    @if(filled($title))
        <h2 class="mb-4 text-balance text-xl font-semibold text-white sm:text-2xl">{{ $title }}</h2>
    @endif
    @if(filled($content))
        <x-tenant.rich-prose variant="default" :content="$content" />
    @endif
</section>
