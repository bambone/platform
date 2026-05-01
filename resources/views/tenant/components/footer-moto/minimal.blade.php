@php
    $p = $f['contact_presentation'] ?? [];
    $systemLinks = $f['system_links'] ?? [];
    $year = $f['year'] ?? (int) now()->year;
    $siteName = $f['site_name'] ?? '';
    $footerTagline = $f['footer_tagline'] ?? '';
    $serviceNote = $f['minimal_service_note'] ?? '';
    $bookingSubline = $f['minimal_booking_subline'] ?? '';
    $expertPrFoot = (bool) ($f['expert_pr_footer'] ?? false);
@endphp
{{-- Слой подвала: полная ширина, без «карточки» по краям; основной блок и нижняя полоса разделены. --}}
<div class="w-full">
    <div class="border-b border-white/[0.06] bg-gradient-to-b from-[rgb(14_16_20)] via-[rgb(8_10_14)] to-[rgb(4_6_10)]">
        {{-- Та же ширина и поля, что у шапки/каталога (max-w-7xl + px-3 sm:px-4 md:px-8), иначе подвал визуально «съезжает» относительно контента. --}}
        <div class="mx-auto max-w-7xl px-3 py-10 sm:px-4 md:px-8 sm:py-12">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-x-14 lg:gap-y-10 xl:gap-x-16">
                {{-- Левая колонка: каналы (заголовок дружелюбнее «Связь») --}}
                <div class="min-w-0 lg:col-span-5">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-moto-amber/90">{{ $expertPrFoot ? 'Contact' : 'На связи' }}</p>
                    <dl class="mt-5 space-y-6">
                        @if(filled($p['phone_href'] ?? ''))
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">{{ $expertPrFoot ? 'Phone' : 'Телефон' }}</dt>
                                <dd class="mt-1.5">
                                    <a href="{{ $p['phone_href'] }}" class="inline-flex min-h-10 items-center text-lg font-semibold tracking-tight text-white transition-colors hover:text-moto-amber">{{ $p['phone_display'] ?? '' }}</a>
                                </dd>
                            </div>
                        @endif
                        @if(filled($p['telegram_url'] ?? ''))
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">Telegram</dt>
                                <dd class="mt-1.5">
                                    <a href="{{ $p['telegram_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center text-base font-medium text-white/95 transition-colors hover:text-moto-amber">{{ $p['telegram_display'] ?? 'Telegram' }}</a>
                                </dd>
                            </div>
                        @endif
                        @if(filled($p['whatsapp_url'] ?? ''))
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">WhatsApp</dt>
                                <dd class="mt-1.5">
                                    <a href="{{ $p['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center text-base font-semibold text-white/95 underline-offset-4 transition hover:text-moto-amber hover:underline">{{ $expertPrFoot ? 'WhatsApp' : 'Написать в WhatsApp' }}</a>
                                </dd>
                            </div>
                        @endif
                        @if(filled($p['vk_url'] ?? ''))
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45">ВКонтакте</dt>
                                <dd class="mt-1.5">
                                    <a href="{{ $p['vk_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 max-w-full items-center break-all text-sm font-medium text-white/90 transition-colors hover:text-moto-amber">{{ $p['vk_url'] }}</a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="min-w-0 lg:col-span-3">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-moto-amber/90">{{ $expertPrFoot ? 'Explore' : 'Разделы сайта' }}</p>
                    @if(! empty($systemLinks))
                        <nav class="mt-5 flex flex-col gap-3" aria-label="{{ $expertPrFoot ? 'Site sections' : 'Служебные ссылки' }}">
                            @foreach($systemLinks as $sl)
                                <a href="{{ $sl['url'] }}" class="inline-flex min-h-10 items-center text-[15px] font-medium text-white/90 underline-offset-4 transition hover:text-moto-amber hover:underline">{{ $sl['label'] }}</a>
                            @endforeach
                        </nav>
                    @else
                        <p class="mt-5 text-sm text-white/40">{{ $expertPrFoot ? 'Links appear when routes are available.' : 'Ссылки появятся, когда маршруты доступны.' }}</p>
                    @endif
                </div>

                {{-- Бронирование: с lg — лёгкий разделитель + inset, чтобы не слипалось со «Разделы сайта». --}}
                <div class="min-w-0 lg:col-span-4 lg:border-l lg:border-white/[0.06] lg:pl-8 xl:pl-10">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-moto-amber/90">{{ $expertPrFoot ? 'Briefs' : 'Бронирование' }}</p>
                    <h3 class="mt-2 text-base font-bold leading-snug tracking-tight text-white">{{ $expertPrFoot ? 'Requests & follow-up' : 'Заявка и подтверждение' }}</h3>
                    @if(filled($serviceNote))
                        <p class="mt-4 max-w-md text-pretty text-[15px] font-normal leading-[1.65] text-white/[0.88]">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord($serviceNote) }}</p>
                    @endif
                    @if(filled($bookingSubline))
                        <p class="mt-3 max-w-md text-pretty text-[13px] leading-relaxed text-white/65">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord($bookingSubline) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Нижняя полоса: копирайт + необязательная подпись из настроек (не дублируем блок выше) --}}
    <div class="border-t border-white/[0.07] bg-black/40">
        <div class="mx-auto max-w-7xl px-3 py-4 sm:px-4 md:px-8">
            <div class="flex flex-col gap-1.5 sm:flex-row sm:flex-wrap sm:items-baseline sm:justify-between sm:gap-x-8 sm:gap-y-1">
                <p class="text-pretty text-[13px] font-semibold text-white/80">© {{ $year }} {{ $siteName }}</p>
                @if(filled($footerTagline))
                    <p class="max-w-2xl text-pretty text-[11px] leading-snug text-white/45 sm:text-right">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord($footerTagline) }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
