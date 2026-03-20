<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- PWA Settings -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0c0c0e">
    <link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192.png') }}">

    <title>{{ config('app.name', 'Moto Levins') }}</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="antialiased bg-obsidian text-silver selection:bg-moto-amber selection:text-white">
    
    <x-header />

    <!-- Main Content -->
    <main class="pb-32 sm:pb-0">
        @yield('content')
    </main>

    <!-- Mobile Messengers (Floating above Sticky CTA) -->
    <div class="fixed right-4 bottom-[calc(88px+env(safe-area-inset-bottom))] z-40 flex flex-col gap-3 sm:hidden">
        @if($contacts['whatsapp'] ?? null)
        <a href="https://wa.me/{{ $contacts['whatsapp'] }}" target="_blank" class="w-12 h-12 bg-[#25D366] text-white flex items-center justify-center rounded-full shadow-lg active:scale-[0.98] transition-transform">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        </a>
        @endif
        @if($contacts['telegram'] ?? null)
        <a href="https://t.me/{{ $contacts['telegram'] }}" target="_blank" class="w-12 h-12 bg-[#0088cc] text-white flex items-center justify-center rounded-full shadow-lg active:scale-[0.98] transition-transform">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
        </a>
        @endif
    </div>

    <!-- Sticky Mobile CTA -->
    <div class="fixed bottom-0 left-0 w-full z-50 bg-white/5 backdrop-blur-xl border-t border-white/10 p-4 sm:hidden pb-[max(1rem,env(safe-area-inset-bottom))]">
        <button onclick="document.getElementById('catalog')?.scrollIntoView({behavior: 'smooth'})" class="w-full bg-moto-amber text-white font-bold h-12 rounded-xl shadow-lg border border-transparent active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
            Забронировать байк
        </button>
    </div>

    <!-- CTAs App -->
    <div class="hidden sm:block">
        <x-contact-cta />
    </div>

    <x-pwa-install-prompt />

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register("{{ asset('sw.js') }}").catch(err => {
                    console.log('SW registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>
