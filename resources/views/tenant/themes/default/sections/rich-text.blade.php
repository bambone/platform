@php
    $h = $data['heading'] ?? '';
    $content = $data['content'] ?? '';
@endphp
<section class="w-full min-w-0 text-sm text-silver sm:text-base">
    @if(filled($h))
        <h2 class="mb-4 text-balance text-xl font-bold text-white sm:text-2xl">{{ $h }}</h2>
    @endif
    @if(filled($content))
        <x-tenant.rich-prose variant="default" :content="$content" />
    @endif
</section>
