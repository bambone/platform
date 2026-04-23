@php
    $d = is_array($data ?? null) ? $data : [];
    $c = (array) ($contacts ?? []);
    $t = (string) ($d['title'] ?? 'Связаться быстро');
    $sub = trim((string) ($d['subheading'] ?? ''));
    $w = (string) ($c['whatsapp'] ?? '');
    $tg = ltrim((string) ($c['telegram'] ?? ''), '@');
    $leadL = (string) ($d['primary_lead_label'] ?? 'Заявка на сайте');
    $leadH = (string) ($d['primary_lead_href'] ?? '/contacts#contact-inquiry');
    $worksL = trim((string) ($d['works_cta_label'] ?? ''));
    $worksH = trim((string) ($d['works_cta_href'] ?? '/raboty'));
@endphp
<section class="bd-section rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-6" aria-label="{{ e($t) }}">
    <h2 class="text-lg font-semibold text-zinc-100">{{ $t }}</h2>
    @if ($sub !== '')
        <p class="mt-1 text-sm text-zinc-400">{{ $sub }}</p>
    @endif
    <div class="mt-4 flex flex-wrap gap-2">
        @if ($leadH !== '' && $leadL !== '')
            <a class="inline-flex min-h-11 items-center rounded-xl bg-[#36C7FF] px-4 text-sm font-semibold text-carbon" href="{{ e($leadH) }}">{{ e($leadL) }}</a>
        @endif
        @if ($worksL !== '' && $worksH !== '')
            <a class="inline-flex min-h-11 items-center rounded-xl border border-white/15 px-4 text-sm font-medium text-zinc-200 hover:bg-white/5" href="{{ e($worksH) }}">{{ e($worksL) }}</a>
        @endif
    </div>
    <div class="mt-4 flex flex-wrap gap-2 border-t border-white/10 pt-4">
        @if (!empty($d['show_whatsapp'] ?? true) && $w !== '')
            <a class="rounded-lg bg-[#25D366]/20 px-3 py-2 text-sm text-[#25D366]" href="https://wa.me/{{ $w }}">WhatsApp</a>
        @endif
        @if (!empty($d['show_telegram'] ?? true) && $tg !== '')
            <a class="rounded-lg bg-[#2AABEE]/20 px-3 py-2 text-sm text-[#2AABEE]" href="https://t.me/{{ $tg }}">Telegram</a>
        @endif
        @if (!empty($d['show_call'] ?? true) && filled($c['phone'] ?? null))
            <a class="rounded-lg border border-white/10 px-3 py-2 text-sm text-zinc-200" href="tel:{{ preg_replace('/\D+/', '', (string) $c['phone']) }}">Позвонить</a>
        @endif
    </div>
</section>
