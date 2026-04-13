@extends('tenant.layouts.app')

@section('title', 'Оформление бронирования')

@section('content')
<section class="mx-auto max-w-2xl px-3 pb-12 pt-24 sm:px-4 sm:pb-16 sm:pt-28 md:px-8">
    <h1 class="sr-only">{{ ($resolvedSeo ?? null)?->h1 ?? 'Оформление бронирования' }}</h1>
    <a href="{{ route('booking.show', $motorcycle->slug) }}" class="mb-6 inline-flex min-h-10 items-center gap-2 text-sm text-silver transition-colors hover:text-white sm:mb-8 sm:text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-moto-amber">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Изменить даты
    </a>

    <div class="glass mb-6 rounded-2xl p-4 sm:p-6">
        <h2 class="mb-4 text-base font-bold text-white sm:text-lg">Ваше бронирование</h2>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-4">
            @if($motorcycle->publicCoverUrl())
                <img src="{{ $motorcycle->publicCoverUrl() }}" alt="{{ $motorcycle->name }}" class="h-24 w-24 shrink-0 rounded-xl object-cover sm:h-28 sm:w-28">
            @endif
            <div class="min-w-0">
                <h3 class="font-bold text-white">{{ $motorcycle->name }}</h3>
                <p class="mt-1 text-sm text-silver">{{ $draft['start_date'] }} — {{ $draft['end_date'] }}</p>
                @php
                    $start = \Carbon\Carbon::parse($draft['start_date']);
                    $end = \Carbon\Carbon::parse($draft['end_date']);
                    $days = $start->diffInDays($end) + 1;
                @endphp
                <p class="text-sm text-silver">{{ $days }} {{ $days === 1 ? 'день' : ($days < 5 ? 'дня' : 'дней') }}</p>
            </div>
        </div>
        @if($addons->isNotEmpty())
            <div class="mt-4 border-t border-white/10 pt-4">
                <p class="mb-2 text-sm text-silver">Дополнительно:</p>
                @foreach($addons as $item)
                    <p class="text-sm text-white">{{ $item->addon->name }} × {{ $item->quantity }} — {{ number_format($item->addon->price * $item->quantity) }} ₽</p>
                @endforeach
            </div>
        @endif
    </div>

    <form action="{{ route('booking.store-checkout') }}" method="POST" class="glass rounded-2xl p-4 sm:p-6 md:p-8">
        @csrf
        <h2 class="mb-5 text-lg font-bold text-white sm:mb-6 sm:text-xl">Контактные данные</h2>

        @if(session('error'))
            <div class="mb-6 rounded-xl border border-red-500/50 bg-red-500/10 p-4 text-sm text-red-400 sm:text-base">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-name">Ваше имя *</label>
                <input id="checkout-name" type="text" name="customer_name" value="{{ old('customer_name') }}" required autocomplete="name" placeholder="Как к вам обращаться"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white placeholder:text-zinc-500 outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber @error('customer_name') border-red-500 @enderror">
                @error('customer_name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-phone">Телефон *</label>
                <input id="checkout-phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"
                    data-rb-intl-phone="1"
                    aria-describedby="checkout-phone-hint"
                    placeholder="+7 (900) 000-00-00" maxlength="28" inputmode="tel"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white placeholder:text-zinc-500 outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber @error('phone') border-red-500 @enderror">
                <p id="checkout-phone-hint" class="mt-2 text-xs leading-snug text-zinc-500 sm:text-sm"></p>
                @error('phone')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @if(count($preferredChannelFormOptions) > 1)
                <fieldset class="rounded-xl border border-white/10 bg-black/25 p-4 sm:p-5">
                    <legend class="mb-1 w-full text-sm font-semibold uppercase tracking-wider text-zinc-500">Как удобнее связаться</legend>
                    <p class="mb-4 text-xs leading-relaxed text-zinc-500">Телефон обязателен. Дополнительно можно указать предпочтительный мессенджер.</p>
                    <div class="flex flex-col gap-2">
                        @foreach($preferredChannelFormOptions as $opt)
                            <label for="checkout-pref-{{ $opt['id'] }}"
                                class="group flex min-h-[3rem] cursor-pointer touch-manipulation items-center gap-3 rounded-xl border border-white/10 bg-black/35 px-4 py-3 transition-colors hover:border-white/[0.14] has-[:checked]:border-moto-amber/55 has-[:checked]:bg-moto-amber/[0.08] has-[:checked]:shadow-[inset_0_0_0_1px_rgba(232,93,4,0.22)] focus-within:ring-2 focus-within:ring-moto-amber/35 focus-within:ring-offset-2 focus-within:ring-offset-[#0f0f12]">
                                <input id="checkout-pref-{{ $opt['id'] }}" type="radio" name="preferred_contact_channel" value="{{ $opt['id'] }}"
                                    class="sr-only"
                                    {{ old('preferred_contact_channel', 'phone') === $opt['id'] ? 'checked' : '' }}>
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-zinc-500/45 bg-[#0c0c0e] transition-colors group-has-[:checked]:border-moto-amber group-has-[:checked]:bg-moto-amber/10" aria-hidden="true">
                                    <span class="h-2 w-2 rounded-full bg-moto-amber opacity-0 shadow-[0_0_10px_rgba(232,93,4,0.55)] transition group-has-[:checked]:opacity-100"></span>
                                </span>
                                <span class="min-w-0 flex-1 text-sm leading-snug text-zinc-300 group-has-[:checked]:font-medium group-has-[:checked]:text-white">{{ $opt['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('preferred_contact_channel')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <div id="checkout-pref-extra" class="mt-4 hidden border-t border-white/[0.08] pt-4">
                        <label class="mb-2 block text-sm text-silver" for="checkout-pref-value">Контакт в мессенджере *</label>
                        <input id="checkout-pref-value" type="text" name="preferred_contact_value" value="{{ old('preferred_contact_value') }}" autocomplete="off"
                            class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white placeholder:text-zinc-500 outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber">
                        <p id="checkout-pref-hint" class="mt-2 text-xs leading-relaxed text-zinc-500"></p>
                        @error('preferred_contact_value')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="preferred_contact_channel" value="phone">
            @endif
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-email">Email</label>
                <input id="checkout-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com"
                    class="h-12 w-full rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white placeholder:text-zinc-500 outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber">
            </div>
            <div>
                <label class="mb-2 block text-sm text-silver" for="checkout-comment">Комментарий</label>
                <textarea id="checkout-comment" name="customer_comment" rows="3" placeholder="Пожелания по времени выдачи и т.п."
                    class="w-full resize-none rounded-xl border border-white/10 bg-black/50 px-4 py-3 text-base text-white placeholder:text-zinc-500 outline-none focus:border-moto-amber focus:ring-1 focus:ring-moto-amber">{{ old('customer_comment') }}</textarea>
            </div>
        </div>

        <button type="submit" class="tenant-btn-primary mt-8 min-h-12 w-full py-3.5 touch-manipulation sm:min-h-14 sm:py-4">
            Подтвердить бронирование
        </button>
    </form>
</section>
@if(count($preferredChannelFormOptions) > 1)
    @php($needsMap = collect($preferredChannelFormOptions)->mapWithKeys(fn ($o) => [$o['id'] => !empty($o['needs_value'])])->all())
    @php($prefHintsMap = collect($preferredChannelFormOptions)->mapWithKeys(fn ($o) => [$o['id'] => (string) ($o['value_hint'] ?? '')])->all())
    @php($prefPlaceholdersMap = collect($preferredChannelFormOptions)->mapWithKeys(fn ($o) => [$o['id'] => (string) ($o['value_placeholder'] ?? '')])->all())
    @php($checkoutPreferredChannelJson = json_encode([
        'needs' => $needsMap,
        'hints' => $prefHintsMap,
        'placeholders' => $prefPlaceholdersMap,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR))
    <script type="application/json" id="checkout-preferred-channel-config">{!! $checkoutPreferredChannelJson !!}</script>
    <script>
        (function () {
            const cfg = JSON.parse(document.getElementById('checkout-preferred-channel-config').textContent);
            const needs = cfg.needs;
            const hints = cfg.hints;
            const placeholders = cfg.placeholders;
            const radios = document.querySelectorAll('input[name="preferred_contact_channel"]');
            const extra = document.getElementById('checkout-pref-extra');
            const input = document.getElementById('checkout-pref-value');
            const hintEl = document.getElementById('checkout-pref-hint');
            function currentChannel() {
                let v = 'phone';
                radios.forEach((r) => { if (r.checked) v = r.value; });
                return v;
            }
            function stripToAscii(s) {
                var N = window.RentBaseVisitorContactNormalize;
                if (N && typeof N.stripToAsciiContactTyping === 'function') {
                    return N.stripToAsciiContactTyping(s);
                }
                return String(s || '').replace(/[^\x20-\x7E]/g, '');
            }
            function needsAsciiChannel() {
                var c = currentChannel();
                return c === 'telegram' || c === 'vk';
            }
            function sync() {
                const v = currentChannel();
                const show = needs[v] === true;
                if (extra) extra.classList.toggle('hidden', !show);
                if (input) input.required = show;
                if (hintEl) {
                    hintEl.textContent = hints[v] || '';
                    hintEl.classList.toggle('hidden', !hints[v]);
                }
                if (input) {
                    input.placeholder = placeholders[v] || '';
                    if (v === 'telegram' || v === 'vk') {
                        input.setAttribute('lang', 'en');
                        input.setAttribute('spellcheck', 'false');
                        input.setAttribute('autocapitalize', 'off');
                    } else {
                        input.removeAttribute('lang');
                        input.removeAttribute('spellcheck');
                        input.removeAttribute('autocapitalize');
                    }
                    if (show && needsAsciiChannel() && input.value) {
                        var st = stripToAscii(input.value);
                        if (st !== input.value) {
                            input.value = st;
                        }
                    }
                }
            }
            if (input && input.dataset.rbAsciiPrefGuard !== '1') {
                input.dataset.rbAsciiPrefGuard = '1';
                function applyStrip() {
                    if (!needsAsciiChannel()) {
                        return;
                    }
                    var v = input.value;
                    var next = stripToAscii(v);
                    if (next === v) {
                        return;
                    }
                    var car = input.selectionStart || 0;
                    input.value = next;
                    var delta = v.length - next.length;
                    var pos = Math.max(0, Math.min(next.length, car - delta));
                    try {
                        input.setSelectionRange(pos, pos);
                    } catch (e) {}
                }
                input.addEventListener('beforeinput', function (e) {
                    if (!needsAsciiChannel()) {
                        return;
                    }
                    if (e.isComposing) {
                        return;
                    }
                    if (e.inputType === 'insertText' && e.data && /[^\x20-\x7E]/.test(e.data)) {
                        e.preventDefault();
                    }
                });
                input.addEventListener('paste', function (e) {
                    if (!needsAsciiChannel()) {
                        return;
                    }
                    var text = (e.clipboardData || window.clipboardData).getData('text') || '';
                    if (!/[^\x20-\x7E]/.test(text)) {
                        return;
                    }
                    e.preventDefault();
                    var cleaned = stripToAscii(text);
                    var start = input.selectionStart || 0;
                    var end = input.selectionEnd || 0;
                    var cur = input.value;
                    input.value = cur.slice(0, start) + cleaned + cur.slice(end);
                    try {
                        input.setSelectionRange(start + cleaned.length, start + cleaned.length);
                    } catch (err) {}
                });
                input.addEventListener('compositionend', applyStrip);
                input.addEventListener('input', applyStrip);
            }
            radios.forEach((r) => r.addEventListener('change', sync));
            sync();
        })();
    </script>
@endif
@endsection
