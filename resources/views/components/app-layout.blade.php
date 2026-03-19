<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0c0c0e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192.png') }}">

    <title>{{ config('app.name', 'Moto Levins') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { theme: { extend: {
                colors: { obsidian: '#0A0A0C', carbon: '#141417', silver: '#A1A1A6', 'moto-amber': '#E85D04' }
            }}};
        </script>
    @endif

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: #ffffff; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.2s !important; transition-duration: 0.2s !important; }
        }
        .premium-bg { background: radial-gradient(circle at 50% -20%, #1a1a1a 0%, #050505 70%); min-height: 100vh; }
        .glass { background: rgba(25, 25, 25, 0.6); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .glass-card { background: rgba(30, 30, 30, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); transition: transform 0.3s ease, background 0.3s ease, border-color 0.3s ease; }
        .glass-card:hover { transform: translateY(-4px); background: rgba(40, 40, 40, 0.6); border-color: rgba(255, 255, 255, 0.15); }
        .bg-accent-gradient { background: linear-gradient(135deg, #FF6B00 0%, #FF3D00 100%); }
        .text-accent-gradient { background: linear-gradient(135deg, #FF8C00 0%, #FF3D00 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="antialiased premium-bg selection:bg-orange-500 selection:text-white pb-32">

    <x-header />

    <main>
        {{ $slot }}
    </main>

    <x-contact-cta />

    <x-pwa-install-prompt />

    @if (!request()->routeIs('offline'))
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
</body>
</html>
