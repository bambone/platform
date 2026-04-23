@php
    $d = is_array($data ?? null) ? $data : [];
    $c = (array) ($contacts ?? []);
    $t = (string) ($d['title'] ?? 'Связаться быстро');
    $w = (string) ($c['whatsapp'] ?? '');
    $tg = ltrim((string) ($c['telegram'] ?? ''), '@');
@endphp
<section class="bd-section" aria-label="{{ e($t) }}">
    <h2 class="text-lg font-semibold text-zinc-100">{{ $t }}</h2>
    <div class="mt-3 flex flex-wrap gap-2">
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
