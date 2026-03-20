# Матрица доступа (Platform / Tenant)

## Роли

| Роль (Spatie / pivot) | Назначение |
|----------------------|------------|
| `platform_owner`, `platform_admin`, `support_manager` | Platform Console (Spatie). Права tenant-пермишенов у этих ролей **пустые** — доступ к данным клиентов только через membership. |
| `tenant_owner`, `tenant_admin`, `booking_manager`, `fleet_manager`, `content_manager`, `operator` | Роль в `tenant_user.pivot.role` + маппинг в `TenantPivotPermissions` для `manage_*` в Tenant Admin. |

Legacy: роль **`super_admin` не используется для входа в панели** (только обратная совместимость в БД). Для входа — `platform_*` и `tenant_user`. См. [SETUP_ADMIN.md](SETUP_ADMIN.md). Роли `admin` / `manager` / `content_manager` в Spatie остаются для совместимости; **вход в Tenant Admin** требует активного pivot в `tenant_user`.

**Документы:** [SETUP_ADMIN.md](SETUP_ADMIN.md) · [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md)

## Зоны

| Зона | Host | Доступ |
|------|------|--------|
| Platform Website | `config('app.platform_host')` | Публично, без `EnsureTenantContext`, без Filament. |
| Platform Console | тот же host, path `/platform` | `EnsurePlatformAccess`: только platform host + platform-роль. |
| Tenant Public | домен из `tenant_domains` | `EnsureTenantContext` + публичные маршруты. |
| Tenant Admin | тот же домен, `/admin` | `canAccessPanel(admin)` + `EnsureTenantMembership` + `Gate::before` по pivot. |

## Таблица role × zone

| role | Platform Website | Platform Console | Tenant Public | Tenant Admin |
|------|------------------|------------------|---------------|--------------|
| platform_* | да (публично) | да | нет | только при **active** `tenant_user` |
| tenant pivot роль | да (как гость) | нет | нет | да (при совпадении host и pivot) |
| без ролей / blocked | да (публично) | нет | нет | нет |

## Три слоя (напоминание)

1. `User::canAccessPanel(Panel)`
2. Middleware (`EnsurePlatformAccess`, `EnsureTenantContext`, `EnsureTenantMembership`)
3. Policies + `Gate::before` (tenant) для `manage_*` / `export_leads`

## Global Search

В обеих панелях **отключён** (`globalSearch(false)`), пока нет tenant-safe / platform-only провайдеров.

## Минимальные access tests (приёмка)

- [ ] Пользователь только с tenant membership **не** открывает Platform Console (даже с URL).
- [ ] Пользователь с `platform_*` **без** `tenant_user` **не** открывает Tenant Admin.
- [ ] Пользователь tenant A **не** видит данные tenant B (прямые URL, экспорт, виджеты).
- [ ] `blocked` user **не** входит ни в одну панель.
- [ ] Запрос на platform host к `/platform` без platform-роли после логина → 403.
- [ ] Неизвестный домен (не platform, не в `tenant_domains`) → страница «Домен не подключён» (404 view).

## Migration impact

См. [A0_FREEZE_SNAPSHOT.md](A0_FREEZE_SNAPSHOT.md): доступ в Tenant Admin только через явный `tenant_user`; `super_admin` не открывает панели.

Часть сценариев покрывается автотестами: `tests/Feature/AccessControlTest.php` (при наличии).
