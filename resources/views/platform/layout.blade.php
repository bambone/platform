<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Платформа') — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; margin: 0; line-height: 1.5; }
        header { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
        nav a { margin-right: 1rem; color: #2563eb; text-decoration: none; }
        main { max-width: 56rem; margin: 0 auto; padding: 2rem 1.5rem; }
        h1 { font-size: 1.75rem; margin-top: 0; }
    </style>
</head>
<body>
<header>
    <nav>
        <a href="{{ route('platform.home') }}">Главная</a>
        <a href="{{ route('platform.features') }}">Возможности</a>
        <a href="{{ route('platform.pricing') }}">Тарифы</a>
        <a href="{{ route('platform.faq') }}">FAQ</a>
        <a href="{{ route('platform.contact') }}">Контакты</a>
    </nav>
</header>
<main>
    @yield('content')
</main>
</body>
</html>
