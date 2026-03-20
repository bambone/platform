# A0 — снимок до разделения Platform / Tenant

Документ фиксирует состояние **до** внедрения плана (rollback / ожидания команды).

## Panel routes (до)

| Зона | Host | Path | Provider |
|------|------|------|----------|
| Tenant Admin | домен из `tenant_domains` (например `motolevins.local`) | `/admin` | `AdminPanelProvider`, id `admin` |
| Platform Console | `config('app.platform_host')` | `/platform` | `PlatformPanelProvider`, id `platform` |

## Ресурсы Filament (до переноса в каталоги)

**Tenant (discovery: `app/Filament/Resources`, исключая подпапку Platform):** Lead, Page, Motorcycle, RentalUnit, User, Faq, Review, Redirect, Integration.

**Platform:** `TenantResource` в `app/Filament/Resources/Platform`.

**Страницы tenant:** `Dashboard` (сток), `Settings` в `app/Filament/Pages`.

**Страницы platform:** `OnboardingWizard` в `app/Filament/Pages/Platform`.

**Виджеты tenant:** `StatsOverviewWidget` и др. в `app/Filament/Widgets`.

## Роли Spatie (до миграции)

- `super_admin`, `admin`, `manager`, `content_manager` — см. `RolePermissionSeeder`.
- Пользователи с `super_admin` обходили проверку membership в `EnsureTenantMembership` (удалено в плане).

**Текущее состояние:** `super_admin` в БД может оставаться для совместимости, но **не даёт доступ в Filament**; рабочая модель — [SETUP_ADMIN.md](SETUP_ADMIN.md) и `AccessRoles`.

## Migration impact (после внедрения)

- У пользователей с ролью `super_admin` она заменяется на **`platform_owner`** (миграция данных).
- **Platform staff** без строки в `tenant_user` **не входят** в Tenant Admin.
- Для доступа к tenant нужен **явный** pivot `tenant_user` со статусом `active` и ролью из разрешённого набора.

## Минимальные access tests (повторить после деплоя)

См. `docs/ACCESS_MATRIX.md` и security checklist в конце того файла.
