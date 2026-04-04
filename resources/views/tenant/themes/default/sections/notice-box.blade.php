@php
    $title = $data['title'] ?? '';
    $text = $data['text'] ?? '';
    $tone = $data['tone'] ?? 'info';
    $boxClass = match ($tone) {
        'warning' => 'border-amber-500/50 bg-amber-500/10 text-amber-50',
        'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-50',
        'neutral' => 'border-white/15 bg-white/[0.05] text-silver',
        default => 'border-sky-500/40 bg-sky-500/10 text-sky-50',
    };
@endphp
<section class="w-full min-w-0" data-page-section-type="{{ $section->section_type }}">
    <div class="rounded-xl border-l-4 p-4 sm:p-5 {{ $boxClass }}">
        @if(filled($title))
            <h3 class="mb-2 text-base font-semibold text-white">{{ $title }}</h3>
        @endif
        @if(filled($text))
            <x-tenant.rich-prose variant="notice" :content="$text" />
        @endif
    </div>
</section>
