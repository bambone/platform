@php
    $d = is_array($data ?? null) ? $data : [];
    if (empty($d['enabled'] ?? true)) { return; }
    $c = (array) ($contacts ?? []);
    $phone = (string) ($c['phone'] ?? '');
    $phoneHref = $phone !== '' ? 'tel:'.preg_replace('/\D+/', '', $phone) : '#';
    $book = (string) ($d['book_anchor'] ?? \App\Tenant\BlackDuck\BlackDuckContentConstants::PRIMARY_LEAD_URL);
    $quote = (string) ($d['quote_anchor'] ?? \App\Tenant\BlackDuck\BlackDuckContentConstants::PRIMARY_LEAD_URL);
@endphp
<nav
    class="bd-sticky-dock pointer-events-auto fixed bottom-0 left-0 right-0 z-40 border-t border-white/10 bg-[#0A1220]/95 px-2 py-2 backdrop-blur sm:hidden"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom, 0px));"
    aria-label="Быстрые действия"
>
    <ul class="flex items-stretch justify-between gap-1 text-center text-[0.7rem] font-medium text-zinc-200">
        <li class="min-w-0 flex-1"><a class="block rounded-lg px-1 py-2 hover:bg-white/5" href="{{ $phoneHref }}">{{ (string) ($d['label_call'] ?? 'Позвонить') }}</a></li>
        <li class="min-w-0 flex-1"><a class="block rounded-lg px-1 py-2 hover:bg-white/5" href="https://wa.me/{{ (string) ($c['whatsapp'] ?? '') }}">{{ (string) ($d['label_messenger'] ?? 'Написать') }}</a></li>
        <li class="min-w-0 flex-1"><a class="block rounded-lg px-1 py-2 text-[#F0FF00] hover:bg-white/5" href="{{ $book }}">{{ (string) ($d['label_book'] ?? 'Запись') }}</a></li>
        <li class="min-w-0 flex-1"><a class="block rounded-lg px-1 py-2 text-[#36C7FF] hover:bg-white/5" href="{{ $quote }}">{{ (string) ($d['label_quote'] ?? 'Расчёт') }}</a></li>
    </ul>
</nav>
