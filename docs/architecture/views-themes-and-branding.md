# Публичный слой: маркетинг, движок tenant, темы и брендинг

Один Laravel-приложение, один `public/`. Разделение по **хостам** и каталогам views.

## Каталоги views (целевая схема)

```
resources/views/
├── platform/
│   ├── marketing/           # лендинг central domains (Route::view)
│   └── layouts/marketing.blade.php
├── tenant/
│   ├── layouts/app.blade.php
│   ├── pages/               # CMS, статика
│   ├── booking/
│   ├── components/          # Blade::anonymousComponentPath
│   ├── sitemap.blade.php
│   └── themes/              # пресеты (не по slug клиента)
│       ├── default/pages/
│       ├── moto/pages/
│       └── auto/pages/
├── errors/                  # domain-not-connected и др.
└── filament/
```

**Запрещено:** `resources/views/tenants/{slug}/` — дублирование кода по клиентам.

## Storage загрузок

```
storage/app/public/tenants/{tenant_id}/logo|favicon|hero|…
```

`php artisan storage:link` → URL `/storage/...`. В шаблонах — хелперы из `app/helpers.php` (`tenant_branding_*`).

## Platform marketing

- **Маршруты:** `routes/web.php` — `Route::view` на central domains → `platform.marketing.*`.
- **Шаблоны:** `resources/views/platform/marketing/*`.
- **SEO / AI (чеклист, robots, sitemap, JSON-LD):** [seo-ai-discoverability.md](seo-ai-discoverability.md).

## Публичный сайт клиента

- **Резолв шаблонов:** хелпер **`tenant_view($logical, $data)`** вызывает **`App\Services\Tenancy\TenantViewResolver`**. Логические имена: `pages.home`, `pages.page`, `pages.motorcycle`, `pages.contacts`, `pages.faq`, `pages.about`, `booking.index` и др.
- **`TenantPublicPageController`** — allowlist для `/contacts`, `/faq`, `/about`.
- Часть маршрутов (terms, prices, offline и т.д.) может оставаться на прямых `view()` без resolver — намеренно ограничивали объём изменений.
- **`theme_key`:** колонка `tenants.theme_key`, whitelist в Platform `TenantResource`: `default`, `moto`, `auto`. Тема **не** выводится из slug клиента.
- **Filament:** Tenant Admin → **Настройки** — `TenantSetting` + загрузки в storage; ключи вроде `branding.logo_path` (см. маппинг в `Settings` странице).

## Цепочка fallback резолвера

1. `tenant.themes.{theme_key}.{logical}`
2. `tenant.themes.default.{logical}`
3. `tenant.{logical}` (движок)

Дубликаты в цепочке убираются, если шаги совпадают.

**Контракт логического имени:** сегменты `[a-z0-9_-]+`, через `.`, без `..` и заглавных (детали и валидация в `TenantViewResolver`).

**Отладка:** только явный флаг `TENANCY_LOG_VIEW_RESOLUTION=true` — лог `tenant_view_resolved` (не завязываем на `APP_DEBUG`, чтобы не забивать `laravel.log`).

## Тесты

- `tests/Unit/TenantViewResolverTest.php`
- `tests/Feature/TenantThemeViewResolverTest.php`
- `tests/Feature/FilamentPanelRoutesTest.php`
- `tests/Feature/HostRoutingSplitTest.php`

## Ручная проверка (кратко)

- Маркетинг: central domain открывает `platform.marketing.*`.
- Тема **moto:** главная может содержать маркер `data-tenant-theme="moto"` (см. тесты).
- После загрузки logo в Настройках — URL вида `/storage/tenants/{id}/logo/...` на публичном сайте.
