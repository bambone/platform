@php
    /** @var array $footer from {@see \App\Services\Tenancy\TenantAdvocateEditorialFooterData} */
    $f = $footer;
    $telDigits = isset($contacts['phone']) && $contacts['phone'] !== ''
        ? (preg_replace('/\D/', '', $contacts['phone']) ?? '')
        : '';
    $hasPractices = ! empty($f['practice_items'] ?? []);
    $footerGridClass = $hasPractices ? 'advocate-site-footer__grid--with-practices' : 'advocate-site-footer__grid--no-practices';
@endphp
<footer class="advocate-site-footer relative z-10 mt-16 w-full min-w-0 border-t border-stone-300/80 bg-[#e8e2d8]/90 text-stone-800 shadow-[0_-12px_40px_rgba(28,31,38,0.06)] backdrop-blur-sm" role="contentinfo" aria-labelledby="advocate-footer-heading">
    <div class="mx-auto w-full min-w-0 max-w-[100rem] px-4 pb-12 pt-12 md:px-8 lg:px-12 lg:pb-14 lg:pt-14">
        <h2 id="advocate-footer-heading" class="sr-only">Подвал сайта</h2>

        {{-- Сетка задана в tenant-advocate-editorial.css (не зависит от purge Tailwind для lg:grid-cols-12). --}}
        <div class="advocate-site-footer__grid {{ $footerGridClass }}">
            <div class="advocate-site-footer__brand min-w-0 space-y-4">
                @if(($f['brand_mark_url'] ?? '') !== '')
                    <div class="flex items-start gap-3">
                        {{-- Классы и размер как у лого в header (advocate_editorial): плотный знак внутри контейнера. --}}
                        <img src="{{ $f['brand_mark_url'] }}"
                             alt=""
                             width="96"
                             height="96"
                             loading="lazy"
                             decoding="async"
                             class="relative h-11 w-11 shrink-0 rounded-xl bg-[#faf8f5]/95 object-contain p-[3px] shadow-[0_1px_4px_rgba(28,31,38,0.09)] ring-1 ring-stone-300/90 md:h-12 md:w-12" />
                        <div class="min-w-0 pt-0.5">
                            <p class="text-[15px] font-semibold leading-snug tracking-tight text-stone-900">{{ $f['brand_title'] }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-[15px] font-semibold leading-snug tracking-tight text-stone-900">{{ $f['brand_title'] }}</p>
                @endif

                @if(filled($f['brand_blurb'] ?? ''))
                    <div class="text-pretty text-[14px] leading-relaxed text-stone-700 lg:max-w-none">{!! nl2br(e(\App\Support\Typography\RussianTypography::tiePrepositionsPerLine((string) $f['brand_blurb']))) !!}</div>
                @endif

                @if(filled($f['approach_line'] ?? ''))
                    <p class="border-l-2 border-[#9a7b4f]/55 pl-3 text-pretty text-[13px] leading-relaxed text-stone-600 lg:max-w-none">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord((string) $f['approach_line']) }}</p>
                @endif
            </div>

            <nav class="advocate-site-footer__nav min-w-0" aria-label="Навигация по сайту">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-stone-500">Навигация</p>
                <ul class="mt-4 space-y-2.5 text-[14px]">
                    @foreach($f['nav_items'] ?? [] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="advocate-footer-link inline-flex min-h-10 items-center py-0.5 text-stone-800 transition hover:text-stone-950">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @if($hasPractices)
            <nav class="advocate-site-footer__practices min-w-0" aria-label="Направления работы">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-stone-500">Направления</p>
                <ul class="mt-4 space-y-2.5 text-[14px]">
                    @foreach($f['practice_items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="advocate-footer-link inline-flex min-h-10 items-center py-0.5 text-stone-800 transition hover:text-stone-950">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
            @endif

            <div class="advocate-site-footer__contacts min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-stone-500">Контакты</p>
                <ul class="mt-4 space-y-3 text-[14px] leading-snug text-stone-800">
                    @if(filled($contacts['phone'] ?? null) && $telDigits !== '')
                        <li>
                            <a href="tel:{{ $telDigits }}" class="advocate-footer-link inline-flex min-h-10 items-center font-medium">{{ $contacts['phone'] }}</a>
                        </li>
                    @endif
                    @if(filled($contacts['email'] ?? null))
                        <li>
                            <a href="mailto:{{ $contacts['email'] }}" class="advocate-footer-link inline-flex min-h-10 items-center break-all font-medium">{{ $contacts['email'] }}</a>
                        </li>
                    @endif
                    @if(filled($contacts['vk_url'] ?? ''))
                        <li>
                            <a href="{{ $contacts['vk_url'] }}" target="_blank" rel="noopener noreferrer" class="advocate-footer-link inline-flex min-h-10 items-center font-medium">ВКонтакте</a>
                        </li>
                    @endif
                    @if(filled($f['office_address'] ?? ''))
                        <li class="text-pretty text-[13px] leading-relaxed text-stone-700">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord((string) $f['office_address']) }}</li>
                    @endif
                </ul>
                <p class="mt-5">
                    <a href="{{ route('contacts') }}" class="advocate-footer-cta inline-flex min-h-10 items-center justify-center rounded-lg border border-stone-400/70 bg-[#faf8f5]/90 px-4 text-[13px] font-semibold text-stone-900 shadow-sm transition hover:border-stone-500 hover:bg-white">Связаться</a>
                </p>
            </div>
        </div>

        <div class="advocate-site-footer__legal mt-12 border-t border-stone-300/70 pt-8">
            <div class="flex flex-col gap-4 text-[12px] leading-relaxed text-stone-600 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-8">
                <p class="text-stone-700">© {{ $f['year'] }} {{ $f['copyright_holder'] }}</p>
                @if(! empty($f['legal_items']))
                    <ul class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-2">
                        @foreach($f['legal_items'] as $legal)
                            <li>
                                <a href="{{ $legal['url'] }}" class="advocate-footer-link min-h-10 underline decoration-stone-400/80 underline-offset-4 transition hover:decoration-stone-700 hover:text-stone-900 sm:inline-flex sm:items-center">{{ $legal['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if(filled($f['disclaimer'] ?? ''))
                <p class="mt-4 max-w-3xl text-pretty text-[11px] leading-relaxed text-stone-500">{{ \App\Support\Typography\RussianTypography::tiePrepositionsToNextWord((string) $f['disclaimer']) }}</p>
            @endif
        </div>
    </div>
</footer>
