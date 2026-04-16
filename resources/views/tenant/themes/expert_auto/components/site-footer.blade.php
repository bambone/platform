@php
    /** @var array $footer from {@see \App\Services\Tenancy\TenantExpertAutoFooterData} */
    $f = $footer;
    $telDigits = isset($contacts['phone']) && $contacts['phone'] !== ''
        ? (preg_replace('/\D/', '', $contacts['phone']) ?? '')
        : '';
    $hasContactBlock = ($telDigits !== '')
        || filled($contacts['email'] ?? null)
        || filled($contacts['telegram'] ?? null)
        || filled($contacts['vk_url'] ?? null)
        || filled($contacts['whatsapp'] ?? null)
        || filled($f['office_address'] ?? '');
@endphp
<footer class="relative z-10 mt-10 w-full min-w-0 border-t border-white/[0.06] sm:mt-12" role="contentinfo" aria-labelledby="expert-auto-footer-heading">
    <div class="mx-auto w-full min-w-0 max-w-[100rem] px-4 pb-10 pt-8 md:px-8 lg:px-12 lg:pb-12 lg:pt-10">
        <h2 id="expert-auto-footer-heading" class="sr-only">Подвал сайта</h2>

        @if($hasContactBlock)
            <div class="expert-auto-site-footer__card rounded-[1.25rem] border border-white/[0.08] bg-[rgb(18_22_28)]/95 p-6 shadow-[0_20px_50px_-24px_rgba(0,0,0,0.65)] sm:p-8 md:p-10">
                <h3 class="text-balance text-2xl font-bold tracking-tight text-white sm:text-3xl">Контакты</h3>

                <div class="mt-8 grid gap-10 md:grid-cols-2 md:gap-12 lg:gap-16">
                    <div class="min-w-0 space-y-8">
                        @if($telDigits !== '')
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/45">Позвонить</p>
                                <a href="tel:{{ $telDigits }}" class="mt-2 inline-flex min-h-10 items-center text-[15px] font-medium text-white transition-colors hover:text-moto-amber">{{ $contacts['phone'] }}</a>
                            </div>
                        @endif
                        @if(filled($contacts['vk_url'] ?? null))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/45">Написать во ВКонтакте</p>
                                <a href="{{ $contacts['vk_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex min-h-10 max-w-full items-center break-all text-[15px] font-medium text-white underline-offset-4 transition-colors hover:text-moto-amber hover:underline">{{ $contacts['vk_url'] }}</a>
                            </div>
                        @endif
                        @if(filled($f['office_address'] ?? ''))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/45">Адрес и зона выезда</p>
                                <p class="mt-2 text-pretty text-[15px] leading-relaxed text-white/90">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord((string) $f['office_address']) }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 space-y-8">
                        @if(filled($contacts['telegram'] ?? null))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/45">Написать в Telegram</p>
                                <a href="https://t.me/{{ $contacts['telegram'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex min-h-10 items-center text-[15px] font-medium text-white transition-colors hover:text-moto-amber">{{ '@'.$contacts['telegram'] }}</a>
                            </div>
                        @endif
                        @if(filled($contacts['email'] ?? null))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/45">Написать на почту</p>
                                <a href="mailto:{{ $contacts['email'] }}" class="mt-2 inline-flex min-h-10 max-w-full items-center break-all text-[15px] font-medium text-white transition-colors hover:text-moto-amber">{{ $contacts['email'] }}</a>
                            </div>
                        @endif
                        @if(filled($contacts['whatsapp'] ?? null))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/45">WhatsApp</p>
                                <a href="https://wa.me/{{ $contacts['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex min-h-10 items-center text-[15px] font-medium text-white transition-colors hover:text-moto-amber">Написать в WhatsApp</a>
                            </div>
                        @endif
                    </div>
                </div>

                <p class="mt-8 border-t border-white/[0.06] pt-6">
                    <a href="{{ route('contacts') }}" class="inline-flex min-h-10 items-center text-[14px] font-semibold text-moto-amber underline-offset-4 transition hover:underline">Страница контактов и форма связи</a>
                </p>
            </div>
        @endif

        @if(! empty($f['nav_items'] ?? []))
            <nav class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-[13px] text-white/70" aria-label="Навигация в подвале">
                @foreach($f['nav_items'] as $item)
                    <a href="{{ $item['url'] }}" class="inline-flex min-h-9 items-center underline-offset-4 transition hover:text-white hover:underline">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <div class="mt-8 flex flex-col gap-3 border-t border-white/[0.06] pt-6 text-[12px] leading-relaxed text-white/50 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <p class="text-pretty text-white/60">© {{ $f['year'] }} {{ $f['copyright_holder'] }}</p>
            <p class="text-pretty max-w-prose text-[12px] text-white/45">Свяжемся удобным способом и согласуем формат занятий под ваш запрос.</p>
        </div>
    </div>
</footer>
