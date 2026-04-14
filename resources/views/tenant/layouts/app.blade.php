<!DOCTYPE html>
<html lang="{{ \App\Services\Seo\TenantPublicHtmlLang::attribute(tenant()) }}" class="dark scroll-smooth overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__stripTenantServiceWorker = ! app()->isProduction() || str_ends_with(request()->getHost(), '.local') || in_array(request()->getHost(), ['localhost', '127.0.0.1'], true);
    @endphp
    @if ($__stripTenantServiceWorker)
        {{-- Старый SW из прошлых деплоев продолжает перехватывать навигацию и отдаёт offline при медленном ответе; на *.local / dev снимаем регистрацию и чистим Cache Storage. --}}
        <script>
            (function () {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistrations().then(function (regs) {
                        regs.forEach(function (r) { r.unregister(); });
                    });
                }
                if ('caches' in window) {
                    caches.keys().then(function (keys) {
                        keys.forEach(function (key) { caches.delete(key); });
                    });
                }
            })();
        </script>
    @endif

    @php
        use App\Themes\ThemeRegistry;
        $tenantFavicon = trim($branding['favicon'] ?? '');
        // expert-brand-favicon.svg лежит в bundled-теме expert_auto; у advocate_editorial своего файла нет — берём URL явно из expert_auto.
        if ($tenantFavicon === '' && in_array(tenant()?->themeKey(), ['expert_auto', 'advocate_editorial'], true)) {
            $tenantFavicon = app(ThemeRegistry::class)->assetUrl('expert_auto', 'expert-brand-favicon.svg', tenant());
        }
        $tenantFaviconMime = 'image/png';
        if ($tenantFavicon !== '') {
            $lower = strtolower($tenantFavicon);
            if (str_ends_with($lower, '.svg') || str_contains($lower, '.svg?')) {
                $tenantFaviconMime = 'image/svg+xml';
            } elseif (str_ends_with($lower, '.ico') || str_contains($lower, '.ico?')) {
                $tenantFaviconMime = 'image/x-icon';
            }
        }
        $appleTouchIcon = app(ThemeRegistry::class)->assetUrl('moto', 'icons/icon-192.png', tenant());
        $brandApple = trim((string) ($branding['apple_touch_icon'] ?? ''));
        if ($brandApple !== '') {
            $appleTouchIcon = $brandApple;
        } else {
            $tenantFaviconTrim = trim($branding['favicon'] ?? '');
            if ($tenantFaviconTrim !== '') {
                $appleTouchIcon = $tenantFaviconTrim;
            }
        }
        $brandFavicon16 = trim((string) ($branding['favicon_16'] ?? ''));
        $brandFavicon32 = trim((string) ($branding['favicon_32'] ?? ''));
        $brandFaviconIco = trim((string) ($branding['favicon_ico'] ?? ''));
    @endphp
    @php
        $__hasBrandIconPack = $brandFavicon16 !== '' || $brandFavicon32 !== '';
        $__tenantFaviconDup = $tenantFavicon !== '' && ($tenantFavicon === $brandFavicon32 || $tenantFavicon === $brandFavicon16);
    @endphp
    @if($brandFavicon16 !== '')
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $brandFavicon16 }}">
    @endif
    @if($brandFavicon32 !== '')
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $brandFavicon32 }}">
    @endif
    @if($brandFaviconIco !== '')
        <link rel="icon" href="{{ $brandFaviconIco }}" sizes="any">
    @endif
    @if($tenantFavicon !== '' && (! $__hasBrandIconPack || ! $__tenantFaviconDup))
        <link rel="icon" href="{{ $tenantFavicon }}" type="{{ $tenantFaviconMime }}">
    @endif

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="{{ tenant() && tenant()->themeKey() === 'advocate_editorial' ? '#f4f1eb' : '#0c0c0e' }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="{{ $appleTouchIcon }}">

    <x-seo-meta />

    @php
        $__tenantCdnBase = rtrim((string) config('tenant_storage.public_cdn_base_url', ''), '/');
        $__tenantCdnConnect = '';
        if ($__tenantCdnBase !== '' && preg_match('#^https?://#i', $__tenantCdnBase) === 1) {
            $__pu = parse_url($__tenantCdnBase);
            if (is_array($__pu) && ! empty($__pu['host'])) {
                $__tenantCdnConnect = ($__pu['scheme'] ?? 'https').'://'.$__pu['host'];
                if (isset($__pu['port'])) {
                    $__tenantCdnConnect .= ':'.$__pu['port'];
                }
            }
        }
    @endphp
    @if ($__tenantCdnConnect !== '')
        <link rel="dns-prefetch" href="{{ $__tenantCdnConnect }}">
        <link rel="preconnect" href="{{ $__tenantCdnConnect }}" crossorigin>
    @endif

    @stack('tenant-jsonld')

    @stack('tenant-preload')

    @include('tenant.partials.analytics-head')

    @php
        $bunnyInterCss = 'https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap';
    @endphp
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preload" href="{{ $bunnyInterCss }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="{{ $bunnyInterCss }}" rel="stylesheet"></noscript>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @php
            $__tk = tenant()?->themeKey();
            $__tenantExpertFamily = in_array($__tk, ['expert_auto', 'advocate_editorial'], true);
        @endphp
        @if ($__tenantExpertFamily)
            @php
                $__expertFamilyViteOk = file_exists(public_path('hot'));
                if (! $__expertFamilyViteOk) {
                    $__manifestPath = public_path('build/manifest.json');
                    if (is_file($__manifestPath) && is_readable($__manifestPath)) {
                        $__manifest = json_decode((string) file_get_contents($__manifestPath), true) ?: [];
                        $__needExpertCss = isset($__manifest['resources/css/tenant-expert-auto.css']);
                        $__needAdvocateCss = ($__tk === 'advocate_editorial')
                            && isset($__manifest['resources/css/tenant-advocate-editorial.css']);
                        $__expertFamilyViteOk = isset($__manifest['resources/js/tenant-expert-inquiry-form.js'])
                            && $__needExpertCss
                            && ($__tk === 'expert_auto' || $__needAdvocateCss);
                    }
                }
            @endphp
            @if ($__expertFamilyViteOk)
                @vite(array_values(array_filter([
                    'resources/css/tenant-expert-auto.css',
                    $__tk === 'advocate_editorial' ? 'resources/css/tenant-advocate-editorial.css' : null,
                    'resources/js/tenant-expert-inquiry-form.js',
                ])))
            @endif
        @endif
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { theme: { extend: {
                colors: {
                    obsidian: '#0A0A0C', carbon: '#141417', silver: '#A1A1A6', 'moto-amber': '#E85D04',
                    'cc-wa': '#25D366', 'cc-tg': '#2AABEE', 'cc-vk': '#0077FF', 'cc-ig': '#E4405F',
                    'cc-msg': '#0084FF', 'cc-viber': '#7360f2', 'cc-max': '#7C3AED',
                }
            }}};
        </script>
    @endif

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.14.3/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            if (window.__tenantBookingStoreRegistered) {
                return;
            }
            window.__tenantBookingStoreRegistered = true;

            const STORAGE_KEY = 'tenant_rental_period_v1';

            Alpine.store('tenantBooking', {
                filters: { start_date: '', end_date: '', location: '' },
                isSearching: false,
                catalogAvailability: null,
                _persistTimer: null,

                loadFromStorage() {
                    try {
                        const raw = localStorage.getItem(STORAGE_KEY);
                        if (! raw) {
                            return;
                        }
                        const o = JSON.parse(raw);
                        if (typeof o.start_date === 'string') {
                            this.filters.start_date = o.start_date;
                        }
                        if (typeof o.end_date === 'string') {
                            this.filters.end_date = o.end_date;
                        }
                        if (typeof o.location === 'string') {
                            this.filters.location = o.location;
                        }
                    } catch (e) {}
                    this.sanitizeRentalPeriodFilters();
                },

                /**
                 * Убираем «висящую» дату «по» без «с», перевёрнутый диапазон и просроченные даты — иначе Flatpickr показывает только конец периода.
                 */
                sanitizeRentalPeriodFilters() {
                    const iso = /^\d{4}-\d{2}-\d{2}$/;
                    let s = (this.filters.start_date || '').trim();
                    let e = (this.filters.end_date || '').trim();
                    let changed = false;
                    if (s !== '' && ! iso.test(s)) {
                        s = '';
                        changed = true;
                    }
                    if (e !== '' && ! iso.test(e)) {
                        e = '';
                        changed = true;
                    }
                    const today = new Date().toISOString().slice(0, 10);
                    if (s !== '' && s < today) {
                        s = '';
                        e = '';
                        changed = true;
                    }
                    if (e !== '' && e < today) {
                        e = '';
                        changed = true;
                    }
                    if (e !== '' && s === '') {
                        e = '';
                        changed = true;
                    }
                    if (s !== '' && e !== '' && e < s) {
                        e = '';
                        changed = true;
                    }
                    this.filters.start_date = s;
                    this.filters.end_date = e;
                    if (changed) {
                        this.persist();
                    }
                },

                persist() {
                    try {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify({
                            start_date: this.filters.start_date,
                            end_date: this.filters.end_date,
                            location: this.filters.location,
                        }));
                    } catch (e) {}
                },

                schedulePersist() {
                    clearTimeout(this._persistTimer);
                    this._persistTimer = setTimeout(() => this.persist(), 40);
                },

                onLocationChange() {
                    this.schedulePersist();
                },

                async applyCatalogSearch(options) {
                    const opts = options && typeof options === 'object' ? options : {};
                    const scrollToCatalog = opts.scrollToCatalog !== false;
                    if (! this.filters.start_date || ! this.filters.end_date) {
                        this.catalogAvailability = null;
                        if (typeof window.TenantDatePickers?.openBarStart === 'function') {
                            window.TenantDatePickers.openBarStart();
                        } else {
                            document.getElementById('start_date')?.focus();
                        }

                        return;
                    }
                    this.persist();
                    this.isSearching = true;
                    this.catalogAvailability = null;
                    let allowScroll = scrollToCatalog;
                    try {
                        const today = new Date().toISOString().slice(0, 10);
                        if (
                            this.filters.start_date < today ||
                            this.filters.end_date < this.filters.start_date
                        ) {
                            this.catalogAvailability = null;
                            allowScroll = false;

                            return;
                        }
                        const bikeIdsEl = document.querySelector('[data-bike-ids]');
                        let motorcycleIds = [];
                        if (bikeIdsEl) {
                            try {
                                motorcycleIds = JSON.parse(bikeIdsEl.getAttribute('data-bike-ids') || '[]');
                            } catch (e) {
                                motorcycleIds = [];
                            }
                        }
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        if (csrf && Array.isArray(motorcycleIds) && motorcycleIds.length > 0) {
                            const res = await fetch('/api/tenant/booking/catalog-availability', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    Accept: 'application/json',
                                },
                                body: JSON.stringify({
                                    start_date: this.filters.start_date,
                                    end_date: this.filters.end_date,
                                    motorcycle_ids: motorcycleIds,
                                }),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.availability && typeof data.availability === 'object') {
                                this.catalogAvailability = data.availability;
                            }
                        }
                    } catch (e) {
                        this.catalogAvailability = null;
                    } finally {
                        this.isSearching = false;
                        if (allowScroll) {
                            setTimeout(() => {
                                document.getElementById('catalog')?.scrollIntoView({ behavior: 'smooth' });
                            }, 50);
                        }
                    }
                },

                rentalDayCount() {
                    const f = this.filters;
                    if (! f.start_date || ! f.end_date) {
                        return 0;
                    }
                    const start = new Date(f.start_date);
                    const end = new Date(f.end_date);
                    if (end < start) {
                        return 0;
                    }
                    const MS_PER_DAY = 1000 * 60 * 60 * 24;
                    const utc1 = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
                    const utc2 = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());

                    return Math.floor((utc2 - utc1) / MS_PER_DAY) + 1;
                },

                calculateCardTotalPrice(pricePerDay) {
                    const days = this.rentalDayCount();

                    return days > 0 ? days * Number(pricePerDay) : 0;
                },

                formatPrice(amount) {
                    return new Intl.NumberFormat('ru-RU').format(amount);
                },
            });

            Alpine.store('tenantBooking').loadFromStorage();
        });
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.2s !important; transition-duration: 0.2s !important; }
        }
        .premium-bg { background: radial-gradient(circle at 50% -20%, #1a1a1a 0%, #050505 70%); min-height: 100vh; }
        /* expert_auto: усиленный фон в tenant-expert-auto.css (.expert-auto-theme.premium-bg) */
        .glass { background: rgba(25, 25, 25, 0.6); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .glass-card { background: rgba(30, 30, 30, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); transition: transform 0.3s ease, background 0.3s ease, border-color 0.3s ease; }
        .glass-card:hover { transform: translateY(-4px); background: rgba(40, 40, 40, 0.6); border-color: rgba(255, 255, 255, 0.15); }
        .bg-accent-gradient { background: linear-gradient(135deg, #FF6B00 0%, #FF3D00 100%); }
        .text-accent-gradient { background: linear-gradient(135deg, #FF8C00 0%, #FF3D00 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        /* Duplicate tenant button system for CDN (sync with resources/css/app.css) */
        .tenant-btn-primary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            min-height: 2.75rem; padding: 0.625rem 1rem; border-radius: 0.75rem;
            font-size: 0.875rem; line-height: 1.25rem; font-weight: 700;
            background-color: #e85d04; color: #0c0c0c;
            box-shadow: 0 4px 14px -3px rgba(232, 93, 4, 0.35);
            transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .tenant-btn-primary:hover { background-color: #f97316; color: #0a0a0a; }
        .tenant-btn-primary:focus-visible { outline: 2px solid #fb923c; outline-offset: 2px; }
        .tenant-btn-primary:active:not(:disabled) { transform: scale(0.98); }
        .tenant-btn-primary:disabled { cursor: not-allowed; opacity: 0.5; }
        .tenant-btn-primary svg { flex-shrink: 0; color: inherit; }
        .tenant-btn-secondary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            min-height: 2.75rem; padding: 0.625rem 1rem; border-radius: 0.75rem;
            font-size: 0.875rem; line-height: 1.25rem; font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background-color: rgba(255, 255, 255, 0.06);
            color: #e4e4e7;
            transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .tenant-btn-secondary:hover {
            border-color: rgba(255, 255, 255, 0.28);
            background-color: rgba(255, 255, 255, 0.1);
            color: #fafafa;
        }
        .tenant-btn-secondary:focus-visible { outline: 2px solid #e85d04; outline-offset: 2px; }
        .tenant-btn-secondary:active { transform: scale(0.98); }

        /* Flatpickr — тёмная тема под мотошапку / модалку */
        .flatpickr-calendar.tenant-fp {
            z-index: 200 !important;
            background: #141417;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(232, 93, 4, 0.12);
            color: #e4e4e7;
            font-family: inherit;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-months {
            padding: 0.5rem 0.35rem 0.25rem;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-month {
            color: #fafafa;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-current-month {
            font-weight: 700;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-weekdays {
            background: transparent;
        }
        .flatpickr-calendar.tenant-fp span.flatpickr-weekday {
            color: #a1a1aa;
            font-weight: 600;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-day {
            border-radius: 0.5rem;
            color: #e4e4e7;
            border-color: transparent;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-day:hover,
        .flatpickr-calendar.tenant-fp .flatpickr-day:focus {
            background: rgba(232, 93, 4, 0.2);
            border-color: rgba(232, 93, 4, 0.45);
            color: #fff;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-day.selected,
        .flatpickr-calendar.tenant-fp .flatpickr-day.startRange,
        .flatpickr-calendar.tenant-fp .flatpickr-day.endRange {
            background: #e85d04;
            border-color: #e85d04;
            color: #0c0c0c;
            font-weight: 700;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-day.today {
            border-color: rgba(232, 93, 4, 0.55);
        }
        /* Заявки Lead (новая / в работе) — день выбирается, но видно «уже кто-то запросил» */
        .flatpickr-calendar.tenant-fp .flatpickr-day.tenant-fp-day-pending-request:not(.flatpickr-disabled) {
            box-shadow: inset 0 0 0 2px rgba(232, 93, 4, 0.65);
            background: rgba(232, 93, 4, 0.12);
            font-weight: 600;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-day.flatpickr-disabled,
        .flatpickr-calendar.tenant-fp .flatpickr-day.prevMonthDay,
        .flatpickr-calendar.tenant-fp .flatpickr-day.nextMonthDay {
            color: #52525b;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-prev-month,
        .flatpickr-calendar.tenant-fp .flatpickr-next-month {
            fill: #fafafa;
        }
        .flatpickr-calendar.tenant-fp .flatpickr-prev-month:hover svg,
        .flatpickr-calendar.tenant-fp .flatpickr-next-month:hover svg {
            fill: #e85d04;
        }
        input.tenant-fp-alt {
            width: 100%;
            min-height: 2.75rem;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.625rem 1rem;
            color: #fff;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }
        input.tenant-fp-alt--sm {
            min-height: 2.5rem;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            font-size: 0.8125rem;
        }
        .tenant-fp-alt:focus {
            border-color: rgba(232, 93, 4, 0.55);
            box-shadow: 0 0 0 1px rgba(232, 93, 4, 0.35);
        }
        .tenant-thin-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(232, 93, 4, 0.35) rgba(255, 255, 255, 0.06);
        }
        /* Модалка бронирования: фиксированная высота (vh), затем svh где есть поддержка — иначе min(...svh...) целиком может быть невалиден и height станет auto */
        .tenant-booking-modal-panel {
            height: min(92vh, calc(100vh - 1rem));
            max-height: min(92vh, calc(100vh - 1rem));
        }
        @media (min-width: 640px) {
            .tenant-booking-modal-panel {
                height: min(90vh, calc(100vh - 2rem));
                max-height: min(90vh, calc(100vh - 2rem));
            }
        }
        @supports (height: 1svh) {
            .tenant-booking-modal-panel {
                height: min(92svh, calc(100vh - 1rem));
                max-height: min(92svh, calc(100vh - 1rem));
            }
        }
        @media (min-width: 640px) {
            @supports (height: 1svh) {
                .tenant-booking-modal-panel {
                    height: min(90svh, calc(100vh - 2rem));
                    max-height: min(90svh, calc(100vh - 2rem));
                }
            }
        }
        /* Полоса прокрутки у области формы; контент не обрезается без прокрутки */
        .tenant-booking-modal-scroll {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .tenant-thin-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .tenant-thin-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 999px;
        }
        .tenant-thin-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(232, 93, 4, 0.35);
            border-radius: 999px;
        }
        .tenant-thin-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(232, 93, 4, 0.5);
        }
        /* Подсветка ошибок публичных форм: resources/css/shared/public-form-field-feedback.css (rb-public-field-error-flash) */
    </style>
</head>
@php
    $__tkBody = tenant()?->themeKey();
    $__tenantExpertAuto = $__tkBody === 'expert_auto';
    $__tenantAdvocateEditorial = $__tkBody === 'advocate_editorial';
    $__tenantExpertFamilyBody = $__tenantExpertAuto || $__tenantAdvocateEditorial;
@endphp
<body @class([
    'antialiased premium-bg overflow-x-clip pb-32 sm:pb-0',
    'text-silver' => ! $__tenantAdvocateEditorial,
    'text-stone-800' => $__tenantAdvocateEditorial,
    'expert-auto-theme' => $__tenantExpertAuto,
    'advocate-editorial-theme' => $__tenantAdvocateEditorial,
    'selection:bg-moto-amber selection:text-[#0c0c0c]' => true,
])>
    @include('partials.analytics-yandex-noscript-body')

    <x-header />

    {{-- Корневой x-data: без него Alpine не обрабатывает @click/$dispatch на страницах вне обёртки (например карточка мотоцикла). --}}
    <main class="w-full min-w-0 pb-32 sm:pb-0" x-data="{}">
        @yield('content')
    </main>

    @include('tenant.components.footer')

    @php
        $__fabEnabled = $floating_messenger_buttons_enabled ?? true;
        $__fabWa = filled($contacts['whatsapp'] ?? null);
        $__fabTg = filled($contacts['telegram'] ?? null);
        $__fabVk = filled($contacts['vk_url'] ?? null);
    @endphp
    @if($__fabEnabled && ($__fabWa || $__fabTg || $__fabVk))
    <div class="tenant-floating-chats fixed z-[35] flex flex-col gap-2 sm:hidden {{ $__tenantExpertFamilyBody ? 'expert-auto-floating-chats right-3' : 'right-4 bottom-[calc(88px+env(safe-area-inset-bottom))]' }}">
        @if($__fabWa)
        <a href="https://wa.me/{{ $contacts['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="{{ $__tenantExpertFamilyBody ? 'h-10 w-10 shadow-md' : 'w-12 h-12 shadow-lg' }} bg-[#25D366] text-white flex items-center justify-center rounded-full active:scale-[0.98] transition-transform" aria-label="Написать в WhatsApp">
            <svg class="{{ $__tenantExpertFamilyBody ? 'w-5 h-5' : 'w-6 h-6' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        </a>
        @endif
        @if($__fabTg)
        <a href="https://t.me/{{ $contacts['telegram'] }}" target="_blank" rel="noopener noreferrer" class="{{ $__tenantExpertFamilyBody ? 'h-10 w-10 shadow-md' : 'w-12 h-12 shadow-lg' }} bg-[#0088cc] text-white flex items-center justify-center rounded-full active:scale-[0.98] transition-transform" aria-label="Написать в Telegram">
            <svg class="{{ $__tenantExpertFamilyBody ? 'w-5 h-5' : 'w-6 h-6' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
        </a>
        @endif
        @if($__fabVk)
        <a href="{{ $contacts['vk_url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $__tenantExpertFamilyBody ? 'h-10 w-10 shadow-md' : 'w-12 h-12 shadow-lg' }} bg-[#0077FF] text-white flex items-center justify-center rounded-full active:scale-[0.98] transition-transform" aria-label="Открыть ВКонтакте">
            <svg class="{{ $__tenantExpertFamilyBody ? 'w-5 h-5' : 'w-6 h-6' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.408 0 15.684 0zm2.07 16.538h-1.558c-.59 0-.77-.47-1.83-1.54-.92-.89-1.33-1.01-1.56-1.01-.32 0-.41.09-.41.53v1.41c0 .38-.12.6-1.12.6-1.65 0-3.48-1-4.76-2.86-1.22-1.8-1.77-3.84-1.77-4.25 0-.23.09-.44.53-.44h1.56c.39 0 .54.18.69.6.77 2.22 2.06 4.18 2.59 4.18.2 0 .29-.09.29-.59v-2.37c-.06-1.06-.62-1.15-.62-1.52 0-.18.15-.36.39-.36h2.45c.33 0 .45.18.45.57v3.1c0 .33.15.45.24.45.2 0 .36-.12.73-.48 1.12-1.25 1.91-3.17 1.91-3.17.14-.29.33-.44.73-.44h1.56c.47 0 .58.24.47.57-.2.91-2.1 3.08-2.1 3.08-.18.24-.24.35 0 .62.18.24.79.91 1.21 1.59.38.59.73 1.21.48 1.88z"/></svg>
        </a>
        @endif
    </div>
    @endif

    @if(! $__tenantExpertFamilyBody)
        <div class="fixed bottom-0 left-0 w-full z-50 bg-white/5 backdrop-blur-xl border-t border-white/10 p-4 sm:hidden pb-[max(1rem,env(safe-area-inset-bottom))]">
            <button type="button" onclick="document.getElementById('catalog')?.scrollIntoView({behavior: 'smooth'})" class="tenant-btn-primary w-full min-h-12 text-base">
                В автопарк
            </button>
        </div>
    @else
        {{-- Mobile + hero: нижняя CTA и WA/TG скрыты на первом экране (классы body expert-auto-past-first-screen ниже). --}}
        {{-- Если на странице нет секции формы (нет #expert-sticky-cta), показываем ссылку на заявку с главной --}}
        <div id="expert-sticky-cta-fallback" class="expert-sticky-cta hidden" hidden>
            <div class="expert-sticky-cta__inner">
                <a href="{{ route('home') }}#expert-inquiry" class="expert-sticky-cta__btn tenant-btn-primary flex w-full justify-center rounded-xl py-3 text-[15px] font-bold shadow-md shadow-black/30">{{ $__tenantAdvocateEditorial ? 'Связаться' : 'Записаться' }}</a>
            </div>
        </div>
        <script>
            (function () {
                if (document.getElementById('expert-sticky-cta')) return;
                var fb = document.getElementById('expert-sticky-cta-fallback');
                if (!fb) return;
                fb.classList.remove('hidden');
                fb.removeAttribute('hidden');
            })();
        </script>
    @endif

    @if ($__tenantExpertFamilyBody)
        <script>
            (function () {
                if (!document.querySelector('[data-expert-hero="1"]')) {
                    return;
                }
                var mq = window.matchMedia('(max-width: 1023px)');

                /** Для порога «прошли первый экран» — innerHeight, без visualViewport: иначе на iOS/Android при скролле дёргается высота и класс body, визуально тянет фон героя. */
                function viewportHeightStable() {
                    return window.innerHeight || 640;
                }

                function sync() {
                    if (!mq.matches) {
                        document.body.classList.add('expert-auto-past-first-screen');
                        return;
                    }
                    var past = window.scrollY > Math.max(32, viewportHeightStable() * 0.88);
                    document.body.classList.toggle('expert-auto-past-first-screen', past);
                }

                mq.addEventListener('change', sync);
                window.addEventListener('scroll', sync, { passive: true });
                window.addEventListener('resize', sync);
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', sync);
                } else {
                    sync();
                }
            })();
        </script>
    @endif

    @if(($floating_messenger_buttons_enabled ?? true) && (filled($contacts['whatsapp'] ?? null) || filled($contacts['telegram'] ?? null) || filled($contacts['vk_url'] ?? null)))
    <div class="hidden sm:block">
        <x-contact-cta />
    </div>
    @endif

    <x-pwa-install-prompt />

    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
    @include('tenant.partials.intl-phone')
    <script>
        (function () {
            window.TenantDatePickers = {
                _barStart: null,
                _barEnd: null,
                _modalStart: null,
                _modalEnd: null,
                _modalVm: null,
                _modalPendingRequestDates: new Set(),
                /** Кэш ISO-дат занятости с API — пере-применяем к обоим календарям после minDate / open */
                _modalDisabledDatesIsoSet: new Set(),

                _locale() {
                    return (typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns.ru)
                        ? flatpickr.l10ns.ru
                        : {};
                },

                /**
                 * Flatpickr keeps the real value on a hidden input; altInput is what the user sees.
                 * Move id + autocomplete to the visible field so label[for] and DevTools form audits match the focused control.
                 */
                _bindFlatpickrAltSurface(inst) {
                    const real = inst.input;
                    const alt = inst.altInput;
                    if (! real || ! alt) {
                        return;
                    }
                    const fid = real.getAttribute('id');
                    if (fid) {
                        alt.id = fid;
                        real.removeAttribute('id');
                    }
                    const ac = real.getAttribute('autocomplete');
                    alt.setAttribute('autocomplete', ac !== null ? ac : 'off');
                    if (real.hasAttribute('required')) {
                        alt.setAttribute('aria-required', 'true');
                    }
                    real.setAttribute('tabindex', '-1');
                    real.setAttribute('aria-hidden', 'true');
                },

                _baseOpts(altInputClass) {
                    const self = this;

                    return {
                        locale: this._locale(),
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd.m.Y',
                        altInputClass: altInputClass,
                        allowInput: false,
                        disableMobile: true,
                        onReady(_d, _s, inst) {
                            inst.calendarContainer.classList.add('tenant-fp');
                            self._bindFlatpickrAltSurface(inst);
                        },
                    };
                },

                destroyBar() {
                    if (this._barStart) {
                        this._barStart.destroy();
                        this._barStart = null;
                    }
                    if (this._barEnd) {
                        this._barEnd.destroy();
                        this._barEnd = null;
                    }
                },

                initBar() {
                    if (typeof flatpickr === 'undefined' || typeof Alpine === 'undefined') {
                        return;
                    }
                    const startEl = document.querySelector('input[data-fp-anchor="tenant-bar-start"]');
                    const endEl = document.querySelector('input[data-fp-anchor="tenant-bar-end"]');
                    if (! startEl || ! endEl) {
                        return;
                    }
                    if (startEl._flatpickr) {
                        return;
                    }

                    this.destroyBar();

                    const store = Alpine.store('tenantBooking');
                    const minStr = startEl.getAttribute('data-fp-min') || 'today';
                    const baseBar = this._baseOpts('tenant-fp-alt');

                    this._barEnd = flatpickr(endEl, {
                        ...baseBar,
                        minDate: store.filters.start_date || minStr,
                        defaultDate: (store.filters.start_date && store.filters.end_date) ? store.filters.end_date : undefined,
                        onChange: (selectedDates) => {
                            if (selectedDates[0]) {
                                store.filters.end_date = this._barEnd.formatDate(selectedDates[0], 'Y-m-d');
                                store.schedulePersist();
                            }
                        },
                    });

                    this._barStart = flatpickr(startEl, {
                        ...baseBar,
                        minDate: minStr,
                        defaultDate: store.filters.start_date || undefined,
                        onChange: (selectedDates) => {
                            if (selectedDates[0]) {
                                store.filters.start_date = this._barStart.formatDate(selectedDates[0], 'Y-m-d');
                                store.schedulePersist();
                                this._barEnd.set('minDate', selectedDates[0]);
                                const endSel = this._barEnd.selectedDates[0];
                                if (endSel && endSel < selectedDates[0]) {
                                    this._barEnd.setDate(selectedDates[0]);
                                }
                                requestAnimationFrame(() => this._barEnd.open());
                            }
                        },
                    });
                },

                openBarEnd() {
                    this._barEnd?.open();
                },

                openBarStart() {
                    this._barStart?.open();
                },

                clearBar() {
                    this._barStart?.clear();
                    this._barEnd?.clear();
                },


                _modalFpSetDisable(picker, disableVal) {
                    if (picker && typeof picker.set === 'function') {
                        picker.set('disable', disableVal);
                    }
                },

                _modalFpRedraw(picker) {
                    if (picker && typeof picker.redraw === 'function') {
                        picker.redraw();
                    }
                },

                /** Повторно выставить disable + перерисовать оба модальных календаря (серые дни и оранж. заявки). */
                _modalRefreshDecorations() {
                    const set = this._modalDisabledDatesIsoSet instanceof Set ? this._modalDisabledDatesIsoSet : new Set();
                    const fn = (d) => set.has(this._calendarYmd(d));
                    this._modalFpSetDisable(this._modalStart, [fn]);
                    this._modalFpSetDisable(this._modalEnd, [fn]);
                    this._modalFpRedraw(this._modalStart);
                    this._modalFpRedraw(this._modalEnd);
                },

                setModalDisableDates(isoDates) {
                    this._modalDisabledDatesIsoSet = new Set(isoDates || []);
                    this._modalRefreshDecorations();
                },

                setModalPendingRequestHighlights(isoDates) {
                    this._modalPendingRequestDates = new Set(isoDates || []);
                    this._modalRefreshDecorations();
                },

                clearModalDisableDates() {
                    this._modalDisabledDatesIsoSet = new Set();
                    this._modalFpSetDisable(this._modalStart, []);
                    this._modalFpSetDisable(this._modalEnd, []);
                    this.clearModalPendingRequestHighlights();
                },

                clearModalPendingRequestHighlights() {
                    this._modalPendingRequestDates.clear();
                    this._modalFpRedraw(this._modalStart);
                    this._modalFpRedraw(this._modalEnd);
                },

                /** @param {Date} d */
                _calendarYmd(d) {
                    if (! d || typeof d.getFullYear !== 'function') {
                        return '';
                    }
                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');

                    return `${y}-${m}-${day}`;
                },

                destroyModal() {
                    this._modalPendingRequestDates.clear();
                    this._modalDisabledDatesIsoSet = new Set();
                    this._modalFpSetDisable(this._modalStart, []);
                    this._modalFpSetDisable(this._modalEnd, []);
                    if (this._modalStart && typeof this._modalStart.destroy === 'function') {
                        this._modalStart.destroy();
                    }
                    this._modalStart = null;
                    if (this._modalEnd && typeof this._modalEnd.destroy === 'function') {
                        this._modalEnd.destroy();
                    }
                    this._modalEnd = null;
                    this._modalVm = null;
                },

                initModal(vm) {
                    if (typeof flatpickr === 'undefined') {
                        return;
                    }
                    this.destroyModal();
                    this._modalVm = vm;

                    const startEl = document.querySelector('input[data-fp-anchor="tenant-modal-start"]');
                    const endEl = document.querySelector('input[data-fp-anchor="tenant-modal-end"]');
                    if (! startEl || ! endEl) {
                        return;
                    }

                    const minStr = startEl.getAttribute('data-fp-min') || 'today';
                    const baseEnd = this._baseOpts('tenant-fp-alt tenant-fp-alt--sm');
                    const baseStart = this._baseOpts('tenant-fp-alt tenant-fp-alt--sm');
                    const self = this;
                    /* Flatpickr 4.6: onDayCreate(selectedDates, dateStr, instance, dayElem) — дата в dayElem.dateObj */
                    const onDayCreatePending = (_selectedDates, _dateStr, _fp, dayElem) => {
                        const dateObj = dayElem && dayElem.dateObj;
                        if (! dateObj || self._modalPendingRequestDates.size === 0) {
                            return;
                        }
                        const ymd = self._calendarYmd(dateObj);
                        if (ymd && self._modalPendingRequestDates.has(ymd)) {
                            dayElem.classList.add('tenant-fp-day-pending-request');
                            dayElem.setAttribute('title', 'Уже есть заявка на эту дату (ожидает обработки оператором)');
                        }
                    };
                    const onModalOpen = () => {
                        self._modalRefreshDecorations();
                    };

                    this._modalEnd = flatpickr(endEl, {
                        ...baseEnd,
                        onDayCreate: onDayCreatePending,
                        onOpen: onModalOpen,
                        minDate: vm.form.start_date || minStr,
                        defaultDate: (vm.form.start_date && vm.form.end_date) ? vm.form.end_date : undefined,
                        onChange: (selectedDates) => {
                            if (selectedDates[0] && this._modalVm) {
                                this._modalVm.form.end_date = this._modalEnd.formatDate(selectedDates[0], 'Y-m-d');
                                this._modalVm.calculatePrice();
                                if (typeof this._modalVm.scheduleHintsFetch === 'function') {
                                    this._modalVm.scheduleHintsFetch();
                                }
                            }
                        },
                    });

                    this._modalStart = flatpickr(startEl, {
                        ...baseStart,
                        onDayCreate: onDayCreatePending,
                        onOpen: onModalOpen,
                        minDate: minStr,
                        defaultDate: vm.form.start_date || undefined,
                        onChange: (selectedDates) => {
                            if (selectedDates[0] && this._modalVm) {
                                this._modalVm.form.start_date = this._modalStart.formatDate(selectedDates[0], 'Y-m-d');
                                this._modalVm.calculatePrice();
                                if (typeof this._modalVm.scheduleHintsFetch === 'function') {
                                    this._modalVm.scheduleHintsFetch();
                                }
                                if (this._modalEnd && typeof this._modalEnd.set === 'function') {
                                    this._modalEnd.set('minDate', selectedDates[0]);
                                    self._modalRefreshDecorations();
                                }
                                const endSel = this._modalEnd.selectedDates[0];
                                if (endSel && endSel < selectedDates[0]) {
                                    this._modalEnd.setDate(selectedDates[0]);
                                }
                            }
                        },
                    });
                    if (typeof vm.scheduleHintsFetch === 'function') {
                        vm.scheduleHintsFetch();
                    }
                },
            };
        })();
    </script>

    @if (! request()->routeIs('offline') && app()->isProduction() && ! $__stripTenantServiceWorker)
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register("{{ asset('sw.js') }}").catch(err => {
                    console.warn('SW registration failed:', err);
                });
            });
        }
    </script>
    @endif

    @stack('tenant-scripts')
</body>
</html>
