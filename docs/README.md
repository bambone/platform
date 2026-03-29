# Документация репозитория

Стек: **Laravel 13**, **Filament 5**, **PHP 8.3**. Одно приложение: маркетинг платформы, консоль платформы, публичный сайт клиента и его админка — разделение по **хосту** и **middleware**.

## Операции

| Документ | Содержание |
|----------|------------|
| [setup-access-deploy.md](operations/setup-access-deploy.md) | URL админок (platform vs tenant), домены, установка, hosts/OSPanel, деплой, права на `storage`, типовые ошибки БД/миграций, кастомные домены; **команда клиента** и **матрица прав кабинета** (только Platform Console) |
| [security-and-golive.md](operations/security-and-golive.md) | Роли, зоны доступа, матрица, чеклист перед релизом |

## Архитектура

| Документ | Содержание |
|----------|------------|
| [views-themes-and-branding.md](architecture/views-themes-and-branding.md) | Каталоги views, `TenantViewResolver`, `theme_key`, брендинг в storage |
| [adr.md](architecture/adr.md) | Зафиксированные архитектурные решения (shared DB, `tenant_user`, панели и т.д.) |
| [data-model.md](architecture/data-model.md) | Сводка таблиц и связей (не полная схема каждой колонки) |

## Справочники

| Документ | Содержание |
|----------|------------|
| [ux-glossary.md](reference/ux-glossary.md) | Термины для подписей в UI («Клиент», «Кабинет клиента», роли) |
| [product-roadmap.md](reference/product-roadmap.md) | Фазы развития продукта (что считается сделанным, что в бэклоге) |

## Карта репозитория

| Область | Где в коде |
|---------|------------|
| Консоль платформы (Filament) | `app/Filament/Platform/` |
| Кабинет клиента (Filament) | `app/Filament/Tenant/` |
| Провайдеры панелей | `app/Providers/Filament/` |
| Публичный сайт тенанта | `routes/web.php`, `app/Http/Controllers`, `resources/views/tenant/` |
| Маркетинг платформы | `resources/views/platform/marketing/` |
| Тенантность | `app/Tenant/`, `config/tenancy.php`, middleware `ResolveTenantFromDomain`, `EnsureTenantContext` |
| Роли и доступ | `app/Auth/AccessRoles.php`, `TenantPivotPermissions`, `TenantAbilityRegistry`, политики, `tests/Feature/AccessControlTest.php`, `tests/Feature/TenantPivotPermissionMatrixTest.php` |
| Правила для AI (Cursor) | [`.cursor/rules/`](../.cursor/rules/) — ядро проекта (`motolevins-core`), Blade/a11y, JS lifecycle, Filament/Livewire |

Именованные каталоги вида `resources/views/tenants/{slug}/` **не используются** — темы лежат в `resources/views/tenant/themes/{default\|moto\|auto}/`.
