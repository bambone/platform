@php
    use App\Support\PageRichContent;

    $__d = is_array($data ?? null) ? $data : [];
    $raw = $__d['content'] ?? '';
    $html = $raw !== '' ? PageRichContent::toHtml($raw) : '';
@endphp
@if (filled($html))
    <section class="advocate-contacts-reassurance mb-12 sm:mb-16" aria-label="Дополнительная информация">
        <div class="advocate-contacts-reassurance__inner rounded-[1.5rem] border border-[rgba(154,123,79,0.22)] bg-[linear-gradient(165deg,rgba(255,252,247,0.98)_0%,rgba(245,238,227,0.92)_100%)] px-6 py-8 shadow-[0_20px_56px_-28px_rgba(28,31,38,0.12)] sm:px-10 sm:py-9">
            <div class="advocate-contacts-reassurance__prose rich-prose rb-rich-prose max-w-3xl text-[15px] leading-relaxed text-[rgb(55_60_68)] sm:text-[16px] sm:leading-[1.65] [&_a]:font-semibold [&_a]:text-[rgb(95_72_42)] [&_a]:underline [&_a]:decoration-[rgba(154,123,79,0.45)] [&_a]:underline-offset-2 [&_a]:transition-colors hover:[&_a]:text-[rgb(72_56_36)]">
                {!! $html !!}
            </div>
        </div>
    </section>
@endif
