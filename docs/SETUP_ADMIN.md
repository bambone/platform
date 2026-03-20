# Доступ к админкам (Platform + Tenant)

## Две панели Filament

| Панель | URL (пример) | Доступ |
|--------|----------------|--------|
| **Platform Console** | `https://{PLATFORM_HOST}/platform` (см. `.env`: `PLATFORM_HOST`, по умолчанию `platform.motolevins.local`) | Spatie-роли: `platform_owner`, `platform_admin`, `support_manager` |
| **Tenant Admin** | `https://{домен_из_tenant_domains}/admin` (например `motolevins.local`) | Запись в `tenant_user` со статусом `active` и pivot-ролью из списка tenant-ролей (см. `App\Auth\AccessRoles::TENANT_MEMBERSHIP`) |

Один и тот же пользователь (email) может иметь **и** platform-роль, **и** membership в `tenant_user` — это явная настройка, не «автоматически из одной роли».

## Политика `super_admin`

- Роль **`super_admin` не используется для входа в панели** — оставлена в БД только для обратной совместимости.
- Рабочая модель: **`AccessRoles`** + **`tenant_user`**. См. [ACCESS_MATRIX.md](ACCESS_MATRIX.md).

## Порядок установки

1. `php artisan migrate`
2. Сидеры (или команды ниже):

```bash
php artisan db:seed --class=AdminUserSeeder
```

По умолчанию: `admin@motolevins.local` / `password`, роль `platform_owner`, при наличии tenant `motolevins` — также `tenant_owner` в `tenant_user`.

Переопределение через `.env`:

```env
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=your-secure-password
```

## Bootstrap без tinker (рекомендуется)

```bash
# Первый platform owner (идемпотентно по email)
php artisan platform:create-owner --email=owner@example.com --name="Owner"

# Привязка пользователя к tenant (upsert: нет дубликатов в tenant_user)
php artisan tenant:attach-user --email=user@example.com --tenant=motolevins --role=tenant_owner --status=active
```

Опции `platform:create-owner`: `--password=`, без пароля — интерактивный запрос (или сгенерировать и вывести).

## Редирект после логина

| Сценарий | Куда ведёт после успешного входа |
|----------|----------------------------------|
| Вход на **Platform** (`/platform/login`) | Дашборд Platform (`/platform`) |
| Вход на **Tenant Admin** (`/admin/login`), только tenant | Дашборд tenant (`/admin`) |
| Вход на **Tenant Admin**, у пользователя **есть** platform-роль | Редирект на **Platform Console** (приоритет platform; единая точка «оба права») |

Используйте **нужный URL логина** для своей роли; не полагайтесь на «универсальный» вход.

## Вариант через Filament (без команд)

```bash
php artisan make:filament-user
```

Затем назначьте **platform**-роль и при необходимости выполните `tenant:attach-user`:

```bash
php artisan tinker
>>> $u = \App\Models\User::where('email', '...')->first();
>>> $u->assignRole('platform_owner');
>>> exit
```

Не используйте `super_admin` как рабочую роль для панелей.

## Локальная разработка (hosts)

- Запись для tenant-домена (например `motolevins.local`).
- Для Platform Console — отдельный хост из `PLATFORM_HOST` (например `platform.motolevins.local`) → тот же `127.0.0.1`.

## Прочее

- Миграция данных bikes → motorcycles: `php artisan db:seed --class=MigrationBikesToMotorcyclesSeeder` (при необходимости).
- Приёмка безопасности: [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md).
