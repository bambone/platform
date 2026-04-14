@php
    $heading = $data['heading'] ?? '';
    $body = $data['body'] ?? '';
    $btn = $data['button_text'] ?? '';
    $url = $data['button_url'] ?? '#';
    $btn2 = $data['secondary_button_text'] ?? '';
    $url2 = $data['secondary_button_url'] ?? '#';
@endphp
<section
    class="relative w-full min-w-0 overflow-hidden rounded-[1.75rem] border border-white/10 bg-gradient-to-br from-[#12141c] via-[#0d0f16] to-[#07080c] px-6 py-9 shadow-[0_40px_100px_-40px_rgba(0,0,0,0.65)] ring-1 ring-inset ring-white/[0.07] sm:px-10 sm:py-11"
    data-page-section-type="{{ $section->section_type ?? '' }}"
>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-moto-amber/35 to-transparent" aria-hidden="true"></div>
    @if(filled($heading))
        <h2 class="max-w-3xl text-balance text-xl font-bold leading-snug text-white sm:text-2xl md:text-[1.65rem]">{{ $heading }}</h2>
    @endif
    @if(filled($body))
        <p class="mt-4 max-w-3xl text-pretty text-[15px] leading-relaxed text-silver/80 sm:text-base">{{ $body }}</p>
    @endif
    @if(filled($btn) || filled($btn2))
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            @if(filled($btn))
                <a
                    href="{{ e($url) }}"
                    class="inline-flex min-h-12 min-w-[11rem] items-center justify-center rounded-xl bg-moto-amber/95 px-6 py-3 text-center text-sm font-bold uppercase tracking-wide text-[#0c0c0e] shadow-lg shadow-black/35 transition hover:bg-moto-amber focus-visible:outline focus-visible:ring-2 focus-visible:ring-moto-amber/70"
                >{{ $btn }}</a>
            @endif
            @if(filled($btn2))
                <a
                    href="{{ e($url2) }}"
                    class="inline-flex min-h-12 min-w-[11rem] items-center justify-center rounded-xl border border-white/18 bg-white/[0.06] px-6 py-3 text-center text-sm font-bold uppercase tracking-wide text-white/92 transition hover:border-moto-amber/40 hover:bg-white/[0.09] focus-visible:outline focus-visible:ring-2 focus-visible:ring-white/25"
                >{{ $btn2 }}</a>
            @endif
        </div>
    @endif
</section>
