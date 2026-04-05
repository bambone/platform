@php
    $pm = app(\App\Product\Settings\MarketingContentResolver::class)->resolved();
    $pmInterCss = 'https://fonts.bunny.net/css?family=inter:wght@400;600;700;800&subset=latin,cyrillic&display=swap';
    $pageTitle = trim($__env->yieldContent('title')) ?: 'Главная';
    $fullTitle = $pageTitle.' — '.($pm['brand_name'] ?? 'RentBase');
    $metaDescription = trim($__env->yieldContent('meta_description', ''));
    if ($metaDescription === '') {
        $metaDescription = (string) ($pm['entity_core'] ?? '');
    }
    $canonical = url()->current();
    $pmContactUrl = platform_marketing_contact_url();
    $pmContactUrlLaunch = platform_marketing_contact_url($pm['intent']['launch'] ?? 'launch');
    $pmContactUrlDiscuss = platform_marketing_contact_url($pm['intent']['custom'] ?? 'custom');
    $pmDemoUrl = platform_marketing_demo_url();
    $pmMainNav = [
        ['label' => 'Возможности', 'href' => url('/#vozmozhnosti')],
        ['label' => 'Тарифы', 'href' => url('/#tarify')],
        ['label' => 'Подробнее', 'href' => url('/features')],
        ['label' => 'FAQ', 'href' => url('/faq')],
        ['label' => 'Контакты', 'href' => $pmContactUrl],
    ];
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            try {
                if (window.innerWidth < 768 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    document.documentElement.classList.add('reduced-motion');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-cyrillic-800-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-cyrillic-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ $pmInterCss }}" as="style">
    <link href="{{ $pmInterCss }}" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="{{ $pmInterCss }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ Str::limit(strip_tags($metaDescription), 320, '') }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($metaDescription), 300, '') }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($metaDescription), 200, '') }}">
    @stack('meta')
    <style>
        /* Критический контур hero: быстрее FCP/LCP до основного CSS */
        #hero { position: relative; overflow: hidden; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        #hero-heading { margin: 0; text-wrap: balance; color: #0f172a; font-weight: 800; line-height: 1.1; letter-spacing: -0.025em; font-size: clamp(2.25rem, 5vw, 3.75rem); }
    </style>
    @vite(['resources/css/platform-marketing.css', 'resources/js/platform-marketing.js'])
    @stack('jsonld')
</head>
<body class="{{ trim('pm-body pm-marketing-with-sticky '.trim($__env->yieldContent('body_class'))) }}">
<header data-pm-header class="sticky top-0 z-50 border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <div class="flex min-h-12 items-center justify-between gap-3 py-2.5 md:min-h-14 md:py-3">
            <a href="{{ url('/') }}" class="min-w-0 shrink text-base font-bold tracking-tight text-slate-900 sm:text-lg">{{ $pm['brand_name'] ?? 'RentBase' }}</a>
            <nav class="hidden flex-1 flex-wrap items-center justify-center gap-x-3 gap-y-1 px-2 text-sm font-medium text-slate-700 lg:flex xl:gap-x-4" aria-label="Основное меню">
                @foreach($pmMainNav as $item)
                    <a href="{{ $item['href'] }}" class="whitespace-nowrap rounded-md px-1 py-2 hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                <a href="{{ $pmDemoUrl }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600" data-pm-event="cta_click" data-pm-cta="secondary" data-pm-location="header_desktop">{{ $pm['cta']['secondary'] ?? 'Посмотреть демо' }}</a>
                <a href="{{ $pmContactUrlDiscuss }}" class="hidden text-sm font-medium text-slate-600 hover:text-blue-700 xl:inline">{{ $pm['cta']['discuss'] ?? 'Обсудить проект' }}</a>
                <a href="{{ $pmContactUrlLaunch }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="header_desktop">{{ $pm['cta']['primary'] ?? 'Запустить проект' }}</a>
            </div>
            <button type="button"
                    class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 text-slate-800 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 lg:hidden"
                    data-pm-nav-toggle
                    aria-controls="pm-mobile-menu"
                    aria-expanded="false"
                    aria-label="Открыть меню">
                <svg class="h-6 w-6" data-pm-nav-icon-open fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg class="hidden h-6 w-6" data-pm-nav-icon-close fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="pm-mobile-menu"
             class="hidden border-t border-slate-200 bg-white pb-3 pt-1 lg:hidden"
             data-pm-mobile-menu
             aria-label="Меню сайта">
            <nav class="flex flex-col gap-0.5" aria-label="Мобильное меню">
                @foreach($pmMainNav as $item)
                    <a href="{{ $item['href'] }}" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-base font-medium text-slate-800 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
                <a href="{{ $pmDemoUrl }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50" data-pm-event="cta_click" data-pm-cta="secondary" data-pm-location="header_mobile">{{ $pm['cta']['secondary'] ?? 'Посмотреть демо' }}</a>
                <a href="{{ $pmContactUrlDiscuss }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50" data-pm-event="cta_click" data-pm-cta="consult" data-pm-location="header_mobile">{{ $pm['cta']['discuss'] ?? 'Обсудить проект' }}</a>
                <a href="{{ $pmContactUrlLaunch }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-800" data-pm-event="cta_click" data-pm-cta="primary" data-pm-location="header_mobile">{{ $pm['cta']['primary'] ?? 'Запустить проект' }}</a>
            </div>
        </div>
    </div>
</header>
<main>
    @yield('content')
</main>
<footer class="border-t border-slate-200 bg-slate-100/80">
    <div class="mx-auto max-w-6xl px-3 py-10 sm:px-4 md:px-6">
        <div class="flex flex-col gap-6 md:flex-row md:justify-between">
            <div>
                <div class="font-semibold text-slate-900">{{ $pm['brand_name'] ?? 'RentBase' }}</div>
                <p class="mt-2 max-w-sm text-sm text-slate-600">{!! str_replace([' для ', ' с ', ' в ', ' и ', ' — '], [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '], Str::limit($pm['entity_core'] ?? '', 200)) !!}</p>
            </div>
            <nav class="flex flex-wrap gap-4 text-sm text-slate-700" aria-label="Футер">
                <a href="{{ url('/features') }}" class="hover:text-blue-700">Возможности</a>
                <a href="{{ url('/pricing') }}" class="hover:text-blue-700">Тарифы</a>
                <a href="{{ url('/faq') }}" class="hover:text-blue-700">FAQ</a>
                <a href="{{ $pmContactUrl }}" class="hover:text-blue-700">Контакты</a>
                <a href="{{ url('/for-moto-rental') }}" class="hover:text-blue-700">Прокат мото</a>
                <a href="{{ url('/for-car-rental') }}" class="hover:text-blue-700">Прокат авто</a>
                <a href="{{ url('/for-services') }}" class="hover:text-blue-700">Сервисы по&nbsp;записи</a>
            </nav>
        </div>
        <p class="mt-8 text-center text-xs text-slate-500">&copy; {{ date('Y') }} {{ $pm['brand_name'] ?? 'RentBase' }}</p>
    </div>
</footer>

<div class="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t border-slate-200 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_24px_rgba(15,23,42,0.06)] lg:hidden" data-pm-mobile-sticky-cta role="region" aria-label="Быстрые действия">
    <a href="{{ $pmContactUrlLaunch }}"
       class="flex min-h-11 flex-1 items-center justify-center rounded-lg bg-pm-accent py-2.5 text-center text-sm font-bold text-white hover:bg-pm-accent-hover"
       data-pm-event="cta_click"
       data-pm-cta="primary"
       data-pm-location="sticky_mobile_bar">
        Запустить
    </a>
    <a href="{{ $pmDemoUrl }}"
       class="flex min-h-11 flex-1 items-center justify-center rounded-lg border border-slate-300 bg-white py-2.5 text-center text-sm font-semibold text-slate-800 hover:bg-slate-50"
       data-pm-event="cta_click"
       data-pm-cta="secondary"
       data-pm-location="sticky_mobile_bar">
        Демо
    </a>
</div>

@stack('body_end')
</body>
</html>
