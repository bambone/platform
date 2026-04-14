@php
    use App\PageBuilder\Contacts\ContactChannelsResolver;
    use App\Support\PageRichContent;

    $sections = $page->sections()
        ->where('status', 'published')
        ->where('is_visible', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    $main = $sections->firstWhere('section_key', 'main');
    $mainData = $main && is_array($main->data_json) ? $main->data_json : [];
    $introRaw = $mainData['content'] ?? '';
    $introHtml = $introRaw !== '' ? PageRichContent::toHtml($introRaw) : null;

    $contactsSection = $sections->firstWhere('section_key', 'contacts_block');
    $contactsData = ($contactsSection && is_array($contactsSection->data_json)) ? $contactsSection->data_json : [];
    $contactPresentation = app(ContactChannelsResolver::class)->present($contactsData);

    $rightSection = $sections->firstWhere('section_key', 'contact_core_right');
    $rightRaw = $rightSection && is_array($rightSection->data_json) ? ($rightSection->data_json['content'] ?? '') : '';
    $rightHtml = $rightRaw !== '' ? PageRichContent::toHtml($rightRaw) : '';

    $c = $contacts ?? [];
    if (! is_array($c)) {
        $c = [];
    }
    $phonePlain = (string) ($c['phone'] ?? '');
    $emailPlain = (string) ($c['email'] ?? '');
    $vkUrl = (string) ($c['vk_url'] ?? '');
    $phoneDigits = preg_replace('/\D+/', '', $phonePlain);
    $mapRouteHref = $contactPresentation->hasMap()
        ? $contactPresentation->mapBlock->mapPublicUrl
        : ($contactPresentation->hasAddress()
            ? ('https://yandex.ru/maps/?text='.rawurlencode($contactPresentation->address))
            : '');
@endphp
@extends('tenant.layouts.app')

@section('content')
    <div class="advocate-contacts-page mx-auto w-full max-w-[min(88rem,calc(100vw-1.5rem))] px-3 pb-14 pt-24 sm:px-5 sm:pb-24 sm:pt-28 lg:px-10">
        @if (empty($introHtml))
            <div class="rounded-2xl border border-orange-200 bg-orange-50 px-5 py-4 text-sm text-orange-900">
                Нет текстового вступления: добавьте секцию <span class="font-mono">main</span> (текстовый блок) на странице в кабинете.
            </div>
        @else
            {{-- Hero: светлая editorial-плоскость, без «чёрной ямы» --}}
            <section class="advocate-contacts-hero mb-12 sm:mb-16 lg:mb-[4.5rem]" aria-labelledby="advocate-contacts-hero-title">
                <div class="advocate-contacts-hero__surface relative overflow-hidden rounded-[2rem] border border-[rgba(154,123,79,0.28)] bg-[linear-gradient(168deg,#fdfcfa_0%,#f3ebe0_48%,#ebe1d4_100%)] px-5 py-12 shadow-[0_28px_80px_-36px_rgba(42,36,28,0.22)] sm:px-10 sm:py-14 lg:px-16 lg:py-16">
                    <div class="pointer-events-none absolute -right-20 -top-16 h-56 w-56 rounded-full bg-[rgba(154,123,79,0.12)] blur-3xl" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-20 -left-12 h-48 w-48 rounded-full bg-[rgba(255,255,255,0.65)] blur-3xl" aria-hidden="true"></div>
                    <div class="relative z-10 mx-auto max-w-3xl text-center">
                        <p class="text-[0.68rem] font-bold uppercase tracking-[0.32em] text-[rgb(122_95_58)]">
                            Контакты
                        </p>
                        <h1 id="advocate-contacts-hero-title" class="mt-4 text-balance font-serif text-[clamp(2.1rem,5vw,3.25rem)] font-semibold leading-[1.08] tracking-tight text-[rgb(22_25_30)]">
                            Связаться с адвокатом
                        </h1>
                    </div>
                    <div class="advocate-contacts-hero-prose rich-prose rb-rich-prose relative z-10 mx-auto mt-8 max-w-3xl text-center">
                        {!! $introHtml !!}
                    </div>
                    <div class="relative z-10 mt-11 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                        @if ($phoneDigits !== '')
                            <a
                                href="tel:+{{ $phoneDigits }}"
                                class="advocate-contacts-hero__action advocate-contacts-hero__action--primary inline-flex min-h-[3rem] items-center justify-center rounded-full px-7 text-[15px] font-semibold shadow-[0_14px_32px_-12px_rgba(95,72,42,0.55)] transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.65)]"
                            >
                                Позвонить
                            </a>
                        @endif
                        @if ($emailPlain !== '')
                            <a
                                href="mailto:{{ e($emailPlain) }}"
                                class="advocate-contacts-hero__action inline-flex min-h-[3rem] items-center justify-center rounded-full px-7 text-[15px] font-semibold transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.5)]"
                            >
                                Написать на почту
                            </a>
                        @endif
                        @if ($vkUrl !== '')
                            <a
                                href="{{ e($vkUrl) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="advocate-contacts-hero__action inline-flex min-h-[3rem] items-center justify-center rounded-full px-7 text-[15px] font-semibold transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.5)]"
                            >
                                Написать во ВКонтакте
                            </a>
                        @endif
                        @if ($mapRouteHref !== '')
                            <a
                                href="{{ e($mapRouteHref) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="advocate-contacts-hero__action inline-flex min-h-[3rem] items-center justify-center rounded-full px-7 text-[15px] font-semibold transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.5)]"
                            >
                                Построить маршрут
                            </a>
                        @endif
                    </div>
                </div>
            </section>

            @if ($contactPresentation->shouldRenderShell() || filled($rightHtml) || $contactPresentation->hasMap())
                <section class="advocate-contacts-core mb-14 sm:mb-20" aria-labelledby="advocate-contacts-core-title">
                    <div class="advocate-contacts-core__shell rounded-[2rem] border border-[rgba(28,31,38,0.07)] bg-[linear-gradient(180deg,rgba(255,252,247,0.98)_0%,rgba(248,244,237,0.96)_100%)] p-6 shadow-[0_24px_70px_-34px_rgba(28,31,38,0.16)] sm:p-9 lg:p-11">
                        <div class="grid gap-12 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)] lg:gap-14 xl:gap-[4rem]">
                            {{-- Левая колонка: крупные блоки вместо «таблицы» --}}
                            <div class="min-w-0">
                                @if ($contactPresentation->hasSectionHeading())
                                    <h2 id="advocate-contacts-core-title" class="font-serif text-[clamp(1.45rem,2.4vw,1.85rem)] font-semibold leading-snug tracking-tight text-[rgb(22_25_30)]">
                                        {{ $contactPresentation->title }}
                                    </h2>
                                @endif
                                @if ($contactPresentation->hasDescription())
                                    <p class="mt-3 text-[17px] leading-relaxed text-[rgb(68_74_84)]">
                                        {{ $contactPresentation->description }}
                                    </p>
                                @endif

                                <div class="mt-9 flex flex-col gap-4">
                                    @foreach ($contactPresentation->allUsableChannels() as $ch)
                                        <div class="advocate-contacts-channel rounded-[1.35rem] border border-[rgba(154,123,79,0.15)] bg-[linear-gradient(145deg,rgba(255,255,255,0.92)_0%,rgba(250,246,239,0.88)_100%)] p-5 shadow-[0_8px_28px_-16px_rgba(28,31,38,0.12)] sm:p-6">
                                            <div class="text-[13px] font-semibold uppercase tracking-[0.08em] text-[rgb(122_95_58)]">
                                                {{ $ch->ctaLabel }}
                                            </div>
                                            <div class="mt-2 min-w-0 text-[18px] font-medium leading-snug text-[rgb(28_31_38)] sm:text-[19px]">
                                                <a
                                                    href="{{ $ch->href }}"
                                                    class="break-words text-[rgb(22_25_30)] decoration-[rgba(154,123,79,0.45)] underline-offset-[5px] transition hover:text-[rgb(95_72_42)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.45)]"
                                                    @if ($ch->openInNewTab) target="_blank" rel="{{ $ch->rel }}" @endif
                                                >{{ $ch->displayValue }}</a>
                                                @if (filled($ch->note))
                                                    <span class="mt-2 block text-[15px] font-normal leading-relaxed text-[rgb(90_96_106)]">{{ $ch->note }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @if ($contactPresentation->hasAddress())
                                        <div class="advocate-contacts-channel rounded-[1.35rem] border border-[rgba(154,123,79,0.15)] bg-[linear-gradient(145deg,rgba(255,255,255,0.92)_0%,rgba(250,246,239,0.88)_100%)] p-5 shadow-[0_8px_28px_-16px_rgba(28,31,38,0.12)] sm:p-6">
                                            <div class="text-[13px] font-semibold uppercase tracking-[0.08em] text-[rgb(122_95_58)]">
                                                Адрес
                                            </div>
                                            <p class="mt-2 whitespace-pre-line text-[17px] leading-relaxed text-[rgb(28_31_38)] sm:text-[18px]">
                                                {{ $contactPresentation->address }}
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                @if ($contactPresentation->hasAdditionalNote())
                                    <div class="advocate-contacts-core-footnote mt-9 rounded-[1.25rem] border border-[rgba(154,123,79,0.12)] bg-[rgba(255,252,247,0.75)] px-5 py-5 text-[15px] leading-relaxed text-[rgb(68_74_84)] sm:px-6 sm:text-[16px] sm:leading-[1.65]">
                                        {!! nl2br(e($contactPresentation->additionalNote)) !!}
                                    </div>
                                @endif
                            </div>

                            {{-- Правая колонка: три явных смысловых зоны --}}
                            <div class="min-w-0 lg:pl-2">
                                @if (filled($rightHtml))
                                    <div class="advocate-contacts-right-copy rich-prose rb-rich-prose text-[rgb(42_46_54)] [&_h3]:mb-3 [&_h3]:font-serif [&_h3]:text-[1.2rem] [&_h3]:font-semibold [&_h3]:leading-snug [&_h3]:text-[rgb(22_25_30)] [&_h3+h3]:mt-10 [&_p]:text-[16px] [&_p]:leading-relaxed [&_p]:text-[rgb(68_74_84)] [&_p+p]:mt-3">
                                        {!! $rightHtml !!}
                                    </div>
                                @endif

                                <div class="advocate-contacts-map-block mt-10">
                                    <p class="mt-2 max-w-md text-[15px] leading-relaxed text-[rgb(82_88_99)] sm:text-[16px]">
                                        Офис находится в Челябинске. Открыть адрес в Яндекс Картах можно по кнопке ниже. Приём проводится по предварительной договорённости.
                                    </p>

                                    <div class="mt-6 [&_a]:rounded-2xl [&_a]:bg-[rgb(154_123_79)] [&_a]:font-semibold [&_a]:text-white [&_a]:shadow-[0_18px_36px_-16px_rgba(95,72,42,0.42)] [&_a]:hover:bg-[rgb(130_103_68)] [&_h3]:font-serif [&_h3]:text-[rgb(22_25_30)]">
                                        @if ($contactPresentation->hasMap())
                                            <x-custom-pages.contacts.map-block :view="$contactPresentation->mapBlock" />
                                        @elseif ($contactPresentation->hasAddress() && $mapRouteHref !== '')
                                            <a
                                                href="{{ e($mapRouteHref) }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-2xl bg-[rgb(154_123_79)] px-5 text-[15px] font-semibold text-white shadow-[0_18px_36px_-16px_rgba(95,72,42,0.42)] transition hover:bg-[rgb(130_103_68)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgba(154,123,79,0.55)]"
                                            >
                                                Открыть в Яндекс Картах
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        @endif

        @foreach ($sections as $section)
            @continue(in_array($section->section_key, ['main', 'contacts_block', 'contact_core_right'], true))

            @php
                $secData = is_array($section->data_json) ? $section->data_json : [];
            @endphp
            @if ($section->section_key === 'expert_lead_form')
                @include('tenant.themes.advocate_editorial.sections.expert_lead_form', [
                    'section' => $section,
                    'data' => $secData,
                    'page' => $page,
                    'advocateContactsPage' => true,
                ])
            @elseif ($section->section_key === 'contacts_reassurance')
                @include('tenant.themes.advocate_editorial.partials.contacts-reassurance-band', [
                    'data' => $secData,
                ])
            @elseif ($section->section_key === 'contacts_closing')
                @include('tenant.themes.advocate_editorial.partials.contacts-outro-light', [
                    'section' => $section,
                    'data' => $secData,
                ])
            @else
                @include('tenant.themes.' . tenant()->site_theme_key . '.sections.' . $section->section_type_key, [
                    'section' => $section,
                    'data' => $secData,
                    'page' => $page,
                ])
            @endif
        @endforeach
    </div>
@endsection
