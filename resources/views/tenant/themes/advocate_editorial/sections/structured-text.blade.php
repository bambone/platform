@php
    $title = $data['title'] ?? null;
    $content = $data['content'] ?? '';
    $maxWidth = $data['max_width'] ?? 'prose';
    $widthClass = match ($maxWidth) {
        'wide' => 'max-w-3xl',
        'full' => 'max-w-none',
        default => 'max-w-prose',
    };
@endphp
<section
    class="{{ $widthClass }} mx-auto w-full min-w-0 rounded-2xl border border-[rgb(28_31_38)]/[0.08] bg-gradient-to-b from-[#faf8f5]/90 to-[#f3efe8]/95 px-5 py-7 shadow-[0_16px_44px_-22px_rgba(28,31,38,0.2)] sm:px-8 sm:py-9"
    data-page-section-type="{{ $section->section_type }}"
>
    @if(filled($title))
        <h2 class="mb-4 text-balance text-xl font-bold leading-snug text-[rgb(24_27_32)] sm:text-2xl">{{ $title }}</h2>
    @endif
    @if(filled($content))
        <div class="advocate-structured-text__body">
            <x-tenant.rich-prose variant="default" :content="$content" />
        </div>
    @endif
</section>
