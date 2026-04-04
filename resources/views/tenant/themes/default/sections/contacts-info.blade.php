@php
    $t = isset($data['title']) ? trim((string) $data['title']) : '';
    $desc = $data['description'] ?? '';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';
    $wa = $data['whatsapp'] ?? '';
    $tg = rtrim(ltrim((string) ($data['telegram'] ?? ''), '@'), '/');
    $addr = $data['address'] ?? '';
    $hours = $data['working_hours'] ?? '';
    $mapEmbed = $data['map_embed'] ?? '';
    $mapLink = $data['map_link'] ?? '';
    $waDigits = preg_replace('/\D+/', '', (string) $wa) ?? '';
    $hasMap = filled($mapEmbed) || filled($mapLink);
@endphp
@php
    $hasAnyChannel = filled($phone) || filled($email) || filled($wa) || filled($tg) || filled($addr) || filled($hours);
@endphp
@if($t === '' && ! $hasAnyChannel && ! $hasMap && ! filled($desc))
    {{-- Секция полностью пустая — не рендерим оболочку --}}
@else
<section class="w-full min-w-0 rounded-xl border border-white/10 bg-white/[0.03] p-5 sm:p-6" data-page-section-type="{{ $section->section_type }}">
    @if(filled($t))
        <h2 class="mb-2 text-xl font-semibold text-white sm:text-2xl">{{ $t }}</h2>
    @endif
    @if(filled($desc))
        <p class="mb-6 text-sm leading-relaxed text-silver sm:text-base">{{ $desc }}</p>
    @endif
    <dl class="grid gap-4 text-sm text-silver sm:grid-cols-2 sm:gap-6 sm:text-base">
        @if(filled($phone))
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Телефон</dt>
                <dd class="mt-1">
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="text-white underline decoration-white/30 underline-offset-2 hover:decoration-white focus-visible:outline focus-visible:ring-2 focus-visible:ring-white/40 rounded-sm">{{ $phone }}</a>
                </dd>
            </div>
        @endif
        @if(filled($email))
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Email</dt>
                <dd class="mt-1">
                    <a href="mailto:{{ $email }}" class="text-white underline decoration-white/30 underline-offset-2 hover:decoration-white focus-visible:outline focus-visible:ring-2 focus-visible:ring-white/40 rounded-sm">{{ $email }}</a>
                </dd>
            </div>
        @endif
        @if(filled($wa) && $waDigits !== '')
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">WhatsApp</dt>
                <dd class="mt-1">
                    <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener noreferrer" class="text-white underline decoration-white/30 underline-offset-2 hover:decoration-emerald-400/80 focus-visible:outline focus-visible:ring-2 focus-visible:ring-emerald-500/50 rounded-sm">Написать в WhatsApp</a>
                </dd>
            </div>
        @elseif(filled($wa))
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">WhatsApp</dt>
                <dd class="mt-1 break-all text-white/90">{{ $wa }}</dd>
            </div>
        @endif
        @if(filled($tg))
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Telegram</dt>
                <dd class="mt-1">
                    <a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener noreferrer" class="text-white underline decoration-white/30 underline-offset-2 hover:decoration-sky-400/80 focus-visible:outline focus-visible:ring-2 focus-visible:ring-sky-500/50 rounded-sm">@{{ $tg }}</a>
                </dd>
            </div>
        @endif
        @if(filled($addr))
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Адрес</dt>
                <dd class="mt-1 whitespace-pre-line text-white/90">{{ $addr }}</dd>
            </div>
        @endif
        @if(filled($hours))
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-white/50">Режим работы</dt>
                <dd class="mt-1 whitespace-pre-line text-white/90">{{ $hours }}</dd>
            </div>
        @endif
    </dl>
    @if(filled($mapLink))
        <p class="mt-6">
            <a href="{{ $mapLink }}" class="text-sm font-medium text-primary-300 underline underline-offset-2 hover:text-primary-200 focus-visible:outline focus-visible:ring-2 focus-visible:ring-primary-400/50 rounded-sm" target="_blank" rel="noopener noreferrer">Открыть на карте</a>
        </p>
    @endif
    @if($hasMap)
        <div class="mt-6 overflow-hidden rounded-lg border border-white/10">
            <div class="aspect-video w-full max-h-80 [&_iframe]:h-full [&_iframe]:min-h-[200px] [&_iframe]:w-full">
                @if(filled($mapEmbed))
                    {!! $mapEmbed !!}
                @else
                    <iframe
                        src="{{ $mapLink }}"
                        title="Карта"
                        width="100%"
                        height="100%"
                        class="min-h-[200px] w-full border-0"
                        loading="lazy"
                    ></iframe>
                @endif
            </div>
        </div>
    @else
        <div class="mt-6 rounded-lg border border-dashed border-white/15 bg-white/[0.02] px-4 py-8 text-center sm:px-6">
            <p class="text-sm text-silver/80">Карту можно добавить в настройках блока: вставьте код карты (iframe) или ссылку для открытия.</p>
        </div>
    @endif
</section>
@endif
