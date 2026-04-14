@php
    $heading = $data['heading'] ?? '';
    $body = $data['body'] ?? '';
    $btn = $data['button_text'] ?? '';
    $url = $data['button_url'] ?? '#';
    $btn2 = $data['secondary_button_text'] ?? '';
    $url2 = $data['secondary_button_url'] ?? '#';
@endphp
<section class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-6 sm:p-8" data-page-section-type="{{ $section->section_type ?? '' }}">
    @if(filled($heading))
        <h2 class="text-xl font-bold text-white sm:text-2xl">{{ $heading }}</h2>
    @endif
    @if(filled($body))
        <p class="mt-3 text-silver">{{ $body }}</p>
    @endif
    @if(filled($btn) || filled($btn2))
        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            @if(filled($btn))
                <a href="{{ e($url) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-carbon hover:bg-amber-400">{{ $btn }}</a>
            @endif
            @if(filled($btn2))
                <a href="{{ e($url2) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 bg-white/[0.04] px-5 py-2.5 text-sm font-semibold text-white/90 hover:border-amber-500/40 hover:bg-white/[0.07]">{{ $btn2 }}</a>
            @endif
        </div>
    @endif
</section>
