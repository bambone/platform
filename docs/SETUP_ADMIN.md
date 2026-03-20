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

Если в браузере **`ERR_NAME_NOT_RESOLVED`** для `platform.motolevins.local` — это **не баг приложения**: имя хоста не сопоставлено с IP (нет записи в DNS/`hosts`).

1. **Windows — файл hosts** (от имени администратора): `C:\Windows\System32\drivers\etc\hosts`  
   Добавьте строки с **тем же IP**, на котором уже открывается tenant-сайт (часто `127.0.0.1` для OSPanel):

   ```
   127.0.0.1   motolevins.local
   127.0.0.1   platform.motolevins.local
   ```

   Сохраните файл, при необходимости выполните `ipconfig /flushdns` в командной строке.

2. **OSPanel** — глобально в «Проекты → Псевдонимы» часто только `www.{host}`: поддомен **`platform`** туда **не входит**, его нужно задать отдельно.

   **Вариант A (удобно):** карточка вашего сайта `motolevins.local` → домены/имена хоста → добавьте **второе имя** `platform.motolevins.local` на **тот же каталог** (тот же `public`, что и у основного домена). После сохранения OSPanel обычно сам дописывает запись в hosts и конфиг веб-сервера.

   **Вариант A2 (репозиторий):** в проекте уже есть `.osp/project.ini` с двумя секциями — `[motolevins.local]` и `[platform.motolevins.local]` (одинаковые `web_root` / `project_root`). После `git pull` перезапустите модули OSPanel или обновите проект в панели, чтобы подхватить hosts и vhost.

   **Вариант B:** вкладка **«Файл HOSTS»** в настройках OSPanel — проверьте, что есть строка с `platform.motolevins.local` (или добавьте вручную, см. п. 1).

   **Важно:** в настройках модулей у конкретного проекта должны быть включены **HTTP** и **PHP** (в вашем скрине глобально стоит «Не использовать» — это нормально, если на уровне **сайта** модули заданы явно). Иначе домен не отдаст приложение.

3. **URL Platform Console** после исправления: `https://platform.motolevins.local/platform` (вход: `/platform/login`). Значение хоста можно переопределить в `.env`: `PLATFORM_HOST=...`.

## Прочее

- Миграция данных bikes → motorcycles: `php artisan db:seed --class=MigrationBikesToMotorcyclesSeeder` (при необходимости).
- Приёмка безопасности: [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md).
