@php
    $line = trim((string) ($pm['pricing_bridge_line'] ?? ''));
@endphp
@if($line !== '')
    <section class="border-b border-slate-200 bg-gradient-to-b from-white to-slate-50 py-10 sm:py-12" aria-label="Переход к тарифам">
        <div class="mx-auto max-w-3xl px-4 text-center md:px-6">
            <p class="text-balance text-lg font-extrabold leading-snug text-slate-900 sm:text-xl md:text-2xl">
                {!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], $line) !!}
            </p>
        </div>
    </section>
@endif
