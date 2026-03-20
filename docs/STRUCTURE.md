# Структура проекта Moto Levins

Сайт проката мотоциклов на Черноморском побережье.

## Маршруты

| URL | Страница |
|-----|----------|
| `/` | Главная |
| `/motorcycles` | Каталог мотоциклов |
| `/motorcycles/{slug}` | Карточка мотоцикла |
| `/prices` | Цены |
| `/order` | Заявка на аренду |
| `/reviews` | Отзывы |
| `/terms` | Условия аренды |
| `/faq` | FAQ |
| `/about` | О нас |
| `/articles` | Статьи |
| `/articles/{slug}` | Статья |
| `/delivery/anapa` | Доставка в Анапу |
| `/delivery/gelendzhik` | Доставка в Геленджик |
| `/contacts` | Контакты |

## Структура views

```
resources/views/
├── layouts/
│   ├── app.blade.php          # Основной layout
│   └── partials/
│       ├── header.blade.php
│       ├── footer.blade.php
│       └── nav.blade.php
├── pages/
│   ├── home.blade.php
│   ├── motorcycles/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── prices.blade.php
│   ├── order.blade.php
│   ├── reviews.blade.php
│   ├── terms.blade.php
│   ├── faq.blade.php
│   ├── about.blade.php
│   ├── articles/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── delivery/
│   │   ├── anapa.blade.php
│   │   └── gelendzhik.blade.php
│   └── contacts.blade.php
└── components/
    └── motorcycle-card.blade.php
```

## Секции главной страницы

1. **hero** — заголовок, телефон
2. **quick-order** — форма быстрой заявки (категория, даты)
3. **about-preview** — кратко о прокате
4. **advantages** — преимущества (6 блоков)
5. **motorcycles-preview** — превью каталога
6. **geography** — города (Новороссийск, Анапа, Геленджик, Ростов)
7. **mission** — миссия компании

## Стек

- Laravel 13
- Blade
- Tailwind CSS (Vite)
- Vite

## Запуск

```bash
php artisan serve
npm run dev
```

## Платформа и админки

- **Platform Website** — маршруты на `config('app.platform_host')`, Blade в `resources/views/platform/` (без tenant context).
- **Platform Console** — Filament panel `platform`, код в `app/Filament/Platform/` (Resources, Pages, Widgets).
- **Tenant Admin** — Filament panel `admin`, код в `app/Filament/Tenant/`.

Документация: [ACCESS_MATRIX.md](ACCESS_MATRIX.md), установка и URL: [SETUP_ADMIN.md](SETUP_ADMIN.md), приёмка: [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md).
