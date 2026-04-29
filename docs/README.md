# Документация репозитория

Стек: **Laravel 13**, **Filament 5**, **PHP 8.3**. Одно приложение: маркетинг платформы, консоль платформы, публичный сайт клиента и его админка — разделение по **хосту** и **middleware**.

## Тенанты (референс)

| Документ | Содержание |
|----------|------------|
| [tenants/magas/v1-brand-domains-and-ops.md](tenants/magas/v1-brand-domains-and-ops.md) | Magas (expert_pr): EN-first бренд, canonical host **sergeymagas.com**, nested `/services/...`, `/contacts`, prelaunch `tenant:magas:bootstrap`, CRM-форма, медиа |

## Операции

| Документ | Содержание |
|----------|------------|
| [setup-access-deploy.md](operations/setup-access-deploy.md) | URL админок (platform vs tenant), домены, установка, hosts/OSPanel, деплой, права на `storage`, типовые ошибки БД/миграций, кастомные домены; **команда клиента** и **матрица прав кабинета** (только Platform Console); **чейнджлог продукта** только в кабинете клиента (`/admin/.../whats-new`), данные в central БД, правки в Platform Console |
| [guide-onboarding-booking-notifications-questionnaire.md](operations/guide-onboarding-booking-notifications-questionnaire.md) | Анкета для гида: бриф клиента по **записи/расписанию** и **уведомлениям** (slug `rentbase-appointment-notifications-v1`, приложения A–C) |
| [guide-onboarding-booking-notifications-mapping.md](operations/guide-onboarding-booking-notifications-mapping.md) | Шпаргалка: ответы анкеты → разделы кабинета и сущности; UI: `/admin/site-setup-booking-notifications` (бриф + автоприменение) |
| [guide-brief-vs-ui-vs-applier.md](operations/guide-brief-vs-ui-vs-applier.md) | Полная анкета гида vs поля брифа в кабинете vs `BookingNotificationsBriefingApplier` |
| [master-questionnaire-spec.md](operations/master-questionnaire-spec.md) | Master-questionnaire: 10 блоков, связь с брифом и автоприменением |
| [security-and-golive.md](operations/security-and-golive.md) | Роли, зоны доступа, матрица, чеклист перед релизом |

## Архитектура

| Документ | Содержание |
|----------|------------|
| [views-themes-and-branding.md](architecture/views-themes-and-branding.md) | Каталоги views, `TenantViewResolver`, `theme_key`, брендинг в storage |
| [seo-ai-discoverability.md](architecture/seo-ai-discoverability.md) | SEO / AI: central marketing + tenant public, canonical/robots/sitemap, JSON-LD по типам страниц, crawlers, `llms.txt`, webmaster checklist, антипаттерны |
| [adr.md](architecture/adr.md) | Зафиксированные архитектурные решения (shared DB, `tenant_user`, панели и т.д.) |
| [data-model.md](architecture/data-model.md) | Сводка таблиц и связей (не полная схема каждой колонки) |
| [tenant-cabinet-guide-brief-model.md](architecture/tenant-cabinet-guide-brief-model.md) | Три уровня: полный кабинет / онбординг / автоприменение; ссылки на ветвление и реестры |
| [tenant-onboarding-branching.md](architecture/tenant-onboarding-branching.md) | `desired_branch` vs `effective_branch`, статусы согласованности, MVP-ветки |
| [tenant-admin-field-registry.md](architecture/tenant-admin-field-registry.md) | Стартовый реестр полей кабинета по зонам меню |
| [tenant-onboarding-question-mapping.md](architecture/tenant-onboarding-question-mapping.md) | Маппинг вопросов: apply_class, ветки, `branch_decision_role` |

## Справочники

| Документ | Содержание |
|----------|------------|
| [ux-glossary.md](reference/ux-glossary.md) | Термины для подписей в UI («Клиент», «Кабинет клиента», роли) |
| [af-017-admin-terminology-contract.md](reference/af-017-admin-terminology-contract.md) | Контракт терминологии админок (AF-017): уровни языка, label/helper, аббревиатуры |
| [af-017-admin-copy-inventory.md](reference/af-017-admin-copy-inventory.md) | Журнал правок UI-copy по AF-017 (до/после по итерациям) |
| [af-018-empty-state-contract.md](reference/af-018-empty-state-contract.md) | Контракт пустых состояний админок (AF-018) |
| [af-018-empty-state-inventory.md](reference/af-018-empty-state-inventory.md) | Журнал внедрения empty states по AF-018 |
| [product-roadmap.md](reference/product-roadmap.md) | Фазы развития продукта (что считается сделанным, что в бэклоге) |

## Карта репозитория

| Область | Где в коде |
|---------|------------|
| Консоль платформы (Filament) | `app/Filament/Platform/` |
| Кабинет клиента (Filament) | `app/Filament/Tenant/` |
| Провайдеры панелей | `app/Providers/Filament/` |
| Публичный сайт тенанта | `routes/web.php`, `app/Http/Controllers`, `resources/views/tenant/` |
| Маркетинг платформы | `resources/views/platform/marketing/`; чейнджлог обновлений **tenant-продукта** — только **кабинет клиента** (Filament page `whats-new`), данные в central `platform_product_changelog_entries` (стартовый seed в миграции), редактирование: **Platform Console** → «Чейнджлог продукта»; **«Что нового»** в user menu tenant admin ведёт на эту страницу |
| Тенантность | `app/Tenant/`, `config/tenancy.php`, middleware `ResolveTenantFromDomain`, `EnsureTenantContext` |
| Роли и доступ | `app/Auth/AccessRoles.php`, `TenantPivotPermissions`, `TenantAbilityRegistry`, политики, `tests/Feature/AccessControlTest.php`, `tests/Feature/TenantPivotPermissionMatrixTest.php` |
| Правила для AI (Cursor) | [`.cursor/rules/`](../.cursor/rules/) — ядро (`motolevins-core`), SEO/AI (`seo-ai-discoverability`), Blade/a11y, JS lifecycle, Filament/Livewire |

Именованные каталоги вида `resources/views/tenants/{slug}/` **не используются** — темы лежат в `resources/views/tenant/themes/{default\|moto\|auto}/`.
