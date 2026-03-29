@php
    $pm = config('platform_marketing');
    $pageTitle = trim($__env->yieldContent('title')) ?: 'Главная';
    $fullTitle = $pageTitle.' — '.($pm['brand_name'] ?? 'RentBase');
    $metaDescription = trim($__env->yieldContent('meta_description', ''));
    if ($metaDescription === '') {
        $metaDescription = (string) ($pm['entity_core'] ?? '');
    }
    $canonical = url()->current();
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    @vite(['resources/css/platform-marketing.css', 'resources/js/platform-marketing.js'])
    @stack('jsonld')
</head>
<body class="pm-body">
@php
    $pmMainNav = [
        ['label' => 'Для кого', 'href' => url('/#dlya-kogo')],
        ['label' => 'Возможности', 'href' => url('/#vozmozhnosti')],
        ['label' => 'Тарифы', 'href' => url('/#tarify')],
        ['label' => 'Подробнее', 'href' => url('/features')],
        ['label' => 'Цены', 'href' => url('/pricing')],
        ['label' => 'FAQ', 'href' => url('/faq')],
        ['label' => 'Контакты', 'href' => Route::has('platform.contact') ? route('platform.contact') : url('/contact')],
    ];
    $pmContactUrl = Route::has('platform.contact') ? route('platform.contact') : url('/contact');
@endphp
<header data-pm-header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
    <div class="mx-auto max-w-6xl px-3 sm:px-4 md:px-6">
        <div class="flex min-h-12 items-center justify-between gap-3 py-2.5 md:min-h-14 md:py-3">
            <a href="{{ url('/') }}" class="min-w-0 shrink text-base font-bold tracking-tight text-slate-900 sm:text-lg">{{ $pm['brand_name'] ?? 'RentBase' }}</a>
            <nav class="hidden flex-1 flex-wrap items-center justify-center gap-x-3 gap-y-1 px-2 text-sm font-medium text-slate-700 lg:flex xl:gap-x-4" aria-label="Основное меню">
                @foreach($pmMainNav as $item)
                    <a href="{{ $item['href'] }}" class="whitespace-nowrap rounded-md px-1 py-2 hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                <a href="{{ platform_marketing_demo_url() }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ $pm['cta']['secondary'] ?? 'Посмотреть демо' }}</a>
                <a href="{{ $pmContactUrl }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ $pm['cta']['primary'] ?? 'Запустить проект' }}</a>
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
                <a href="{{ platform_marketing_demo_url() }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">{{ $pm['cta']['secondary'] ?? 'Посмотреть демо' }}</a>
                <a href="{{ $pmContactUrl }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">{{ $pm['cta']['primary'] ?? 'Запустить проект' }}</a>
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
                <p class="mt-2 max-w-sm text-sm text-slate-600">{{ Str::limit($pm['entity_core'] ?? '', 200) }}</p>
            </div>
            <nav class="flex flex-wrap gap-4 text-sm text-slate-700" aria-label="Футер">
                <a href="{{ url('/features') }}" class="hover:text-blue-700">Возможности</a>
                <a href="{{ url('/pricing') }}" class="hover:text-blue-700">Тарифы</a>
                <a href="{{ url('/faq') }}" class="hover:text-blue-700">FAQ</a>
                <a href="{{ url('/contact') }}" class="hover:text-blue-700">Контакты</a>
                <a href="{{ url('/for-moto-rental') }}" class="hover:text-blue-700">Прокат мото</a>
                <a href="{{ url('/for-car-rental') }}" class="hover:text-blue-700">Прокат авто</a>
            </nav>
        </div>
        <p class="mt-8 text-center text-xs text-slate-500">&copy; {{ date('Y') }} {{ $pm['brand_name'] ?? 'RentBase' }}</p>
    </div>
</footer>
</body>
</html>
