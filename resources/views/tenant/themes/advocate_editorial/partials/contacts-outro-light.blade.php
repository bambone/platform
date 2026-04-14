{{-- Лёгкий финальный блок после reassurance: кнопки без тяжёлого тёмного CTA. --}}
@php
    $heading = trim((string) ($data['heading'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $btn = trim((string) ($data['button_text'] ?? ''));
    $url = (string) ($data['button_url'] ?? '#');
    $btn2 = trim((string) ($data['secondary_button_text'] ?? ''));
    $url2 = (string) ($data['secondary_button_url'] ?? '#');
@endphp
<section
    class="advocate-contacts-outro-light mb-6 w-full min-w-0 overflow-hidden rounded-[1.5rem] border border-[rgba(28,31,38,0.08)] bg-white/90 px-6 py-8 shadow-[0_16px_44px_-24px_rgba(28,31,38,0.14)] sm:px-10 sm:py-10"
    data-page-section-type="{{ $section->section_type ?? '' }}"
>
    @if ($heading !== '')
        <h2 class="font-serif text-xl font-semibold tracking-tight text-[rgb(24_27_32)] sm:text-2xl">{{ $heading }}</h2>
    @endif
    @if ($body !== '')
        <p class="mt-3 max-w-3xl text-pretty text-[15px] leading-relaxed text-[rgb(82_88_99)] sm:text-base">{{ $body }}</p>
    @endif
    @if ($btn !== '' || $btn2 !== '')
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            @if ($btn !== '')
                <a
                    href="{{ e($url) }}"
                    class="inline-flex min-h-12 min-w-[11rem] items-center justify-center rounded-xl bg-[rgb(154_123_79)] px-6 py-3 text-center text-sm font-semibold text-white shadow-[0_12px_28px_-12px_rgba(95,72,42,0.45)] transition hover:bg-[rgb(130_103_68)] focus-visible:outline focus-visible:ring-2 focus-visible:ring-[rgba(154,123,79,0.55)]"
                >{{ $btn }}</a>
            @endif
            @if ($btn2 !== '')
                <a
                    href="{{ e($url2) }}"
                    class="inline-flex min-h-12 min-w-[11rem] items-center justify-center rounded-xl border border-[rgba(28,31,38,0.12)] bg-[rgba(248,246,242,0.95)] px-6 py-3 text-center text-sm font-semibold text-[rgb(28_31_32)] transition hover:border-[rgba(154,123,79,0.35)] hover:bg-[#fffefb] focus-visible:outline focus-visible:ring-2 focus-visible:ring-[rgba(154,123,79,0.25)]"
                >{{ $btn2 }}</a>
            @endif
        </div>
    @endif
</section>
