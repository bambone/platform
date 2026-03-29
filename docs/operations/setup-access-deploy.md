# Доступ к админкам, установка и деплой

Полный runbook: **куда заходить**, **как поднять локально**, **что проверить на production**. См. также [security-and-golive.md](security-and-golive.md) и [../README.md](../README.md).

---

## URL на production (Rentbase)

- **Маркетинг (лендинг платформы):** https://rentbase.su и https://www.rentbase.su — хосты из `TENANCY_CENTRAL_DOMAINS`, отдельно от `PLATFORM_HOST`.
- **Platform Console:** https://platform.rentbase.su/platform (хост из `PLATFORM_HOST` в `.env`).
- **Кабинет клиента (tenant admin):** `https://<домен тенанта>/admin` (например https://motolevins.rentbase.su/admin). Списки Filament: `/admin/rental-units` и т.д. — **после входа** на домене клиента. Apex `https://rentbase.su/admin` **не** используется как клиентская админка.

Поддомен **`{slug}.{TENANCY_ROOT_DOMAIN}`** (например `motolevins.rentbase.su`) создаётся при создании тенанта в Platform Console и может досеиваться миграцией `2026_03_28_140000_ensure_motolevins_canonical_subdomain`. Без строки в `tenant_domains` со **статусом «Активен»** сайт покажет «Домен не подключён».

<a id="domain-fields-table"></a>

### Домены в Platform Console («Домены клиентов») — что за поля

| Поле | Зачем |
|------|--------|
| **Статус** | Если не **Активен**, этот хост **не** привязывается к клиенту (сайт и `/admin` по нему не откроются). |
| **Тип: Поддомен** | Адрес на зоне платформы (`*.rentbase.su`). Не переключает сайт «вкл/выкл» — классификация и поля формы. |
| **Тип: Кастомный домен** | Свой домен у регистратора; DNS/SSL. Для `motolevins.rentbase.su` в зоне платформы нужен **Поддомен**, не кастомный. |
| **Основной** | Канонический URL для подсказок; **все** активные домены всё равно работают. |

Переключение **типа** часто «ничего не меняет» в браузере: резолвер смотрит на **хост + активен ли домен**.

### Презентационный слой (views / темы / storage)

Именованные папки `resources/views/tenants/{slug}/` **не используются**. Движок и темы: `resources/views/tenant/themes/*`, детали в [views-themes-and-branding.md](../architecture/views-themes-and-branding.md).

- **Тема сайта:** в Platform Console у клиента — «Тема публичного сайта» (`theme_key`: default / moto / auto).
- **Логотип и иконки:** Tenant Admin → **Настройки** — файлы в `storage` или внешний URL (legacy). Нужен `php artisan storage:link` на сервере.

## Две панели Filament

| Панель | URL (пример) | Доступ |
|--------|----------------|--------|
| **Platform Console** | `https://{PLATFORM_HOST}/platform` (локально часто `platform.motolevins.local`) | Spatie: `platform_owner`, `platform_admin`, `support_manager` |
| **Кабинет клиента** | `https://{домен из tenant_domains}/admin` | `tenant_user` со статусом `active` и ролью из `App\Auth\AccessRoles::TENANT_MEMBERSHIP` |

Один email может иметь **и** platform-роль, **и** membership в `tenant_user`.

## Команда клиента и матрица прав кабинета (только Platform Console)

- **Участники кабинета клиента** (`tenant_user`: роль и статус в команде) заводятся и правятся в **Platform Console** → карточка **«Клиенты»** → вкладка **«Команда клиента»**: добавить существующего пользователя, создать учётку без platform-ролей, сменить роль/статус в команде, отвязать. Ссылка на кабинет `/admin` подставляется из **активного** домена клиента (см. таблицу доменов выше).
- **Какие именно `manage_*` / `export_leads` разрешены для каждой pivot-роли** задаётся матрицей в **Platform Console** → **«Безопасность и роли кабинета»** (`/platform/tenant-cabinet-security`). Значения хранятся в `platform_settings` (`tenant_pivot_permission_matrix`); **сброс к коду** убирает переопределение и снова включает дефолты из `App\Auth\TenantPivotPermissions::defaults()`. Редактировать матрицу могут только **`platform_owner`** и **`platform_admin`**.
- **Вход с `/admin` при двойном доступе:** глобальный флаг в той же странице (`tenant_login_prefer_tenant_panel`) отключает автоматический редирект на консоль платформы для пользователей с platform-ролью — см. строку в таблице редиректов ниже.

## Политика `super_admin`

Роль **`super_admin` не используется для входа в панели** (совместимость в БД). Рабочая модель: **AccessRoles** + **tenant_user**. Подробнее: [security-and-golive.md](security-and-golive.md).

## Порядок установки

1. `php artisan migrate`
2. Сидеры или команды:

```bash
php artisan db:seed --class=AdminUserSeeder
```

По умолчанию (если задано в сидере): `admin@motolevins.local` / `password`, роль `platform_owner`, при наличии tenant `motolevins` — также `tenant_owner` в `tenant_user`.

Переопределение через `.env`:

```env
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=your-secure-password
```

## Bootstrap без tinker (рекомендуется)

```bash
php artisan platform:create-owner --email=owner@example.com --name="Owner"
php artisan tenant:attach-user --email=user@example.com --tenant=motolevins --role=tenant_owner --status=active
```

Опции `platform:create-owner`: `--password=` или интерактивный запрос.

## Редирект после логина

| Сценарий | Куда ведёт |
|----------|------------|
| Вход на **Platform** (`/platform/login`) | `/platform` |
| Вход на **Tenant Admin** (`/admin/login`), только tenant | `/admin` |
| Вход на **Tenant Admin**, у пользователя **есть** platform-роль | Редирект на **Platform Console** (приоритет platform) |
| Вход на **Tenant Admin**, у пользователя **есть** platform-роль, включён **`tenant_login_prefer_tenant_panel`** | Остаётся **Tenant Admin** (`/admin`) |

## Вариант через Filament

```bash
php artisan make:filament-user
```

Затем назначьте platform-роль и при необходимости `tenant:attach-user` (см. выше). Не используйте `super_admin` как рабочую роль.

## Локальная разработка (hosts / OSPanel)

Если **`ERR_NAME_NOT_RESOLVED`** для `platform.motolevins.local` — добавьте в `C:\Windows\System32\drivers\etc\hosts` (от администратора):

```
127.0.0.1   motolevins.local
127.0.0.1   platform.motolevins.local
```

**OSPanel:** поддомен `platform` часто нужно добавить отдельно (второе имя сайта или `.osp/project.ini` в репозитории). На уровне **сайта** должны быть включены HTTP и PHP.

Platform Console: `https://platform.motolevins.local/platform`. Хост задаётся в `.env`: `PLATFORM_HOST=...`.

## Миграция bikes → motorcycles (legacy)

При необходимости: `php artisan db:seed --class=MigrationBikesToMotorcyclesSeeder`.

---

# Деплой и типовые ошибки на production

## Tenant branding (storage)

Файлы брендинга: `storage/app/public/tenants/{tenant_id}/…`.

```bash
php artisan storage:link
```

Опционально: `TENANCY_LOG_VIEW_RESOLUTION=true` в `.env` для отладки выбора Blade-темы (на проде обычно `false`).

<a id="filament-storage-cache-permissions"></a>

## Filament: `Permission denied` в `storage/framework/cache`

**Симптом:** `file_put_contents(.../storage/framework/cache/...): Permission denied` (часто Spatie Permission + Filament tables).

**Причина:** PHP-FPM не может писать в `storage/` или `bootstrap/cache/` (часто после `artisan` от root).

**Исправление** (путь замените на свой):

```bash
cd /var/www/platform
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
```

Не оставляйте файлы проекта с владельцем root после деплоя.

**Альтернатива:** `CACHE_STORE=database` или `redis` — не освобождает от прав на `storage/` для логов, загрузок и views.

## Ошибка `Table '…tenant_domains' doesn't exist`

На сервере не прогнаны миграции в той БД, что в `DB_*`.

```bash
php artisan migrate --force
php artisan migrate:status
```

Сначала миграции, потом `config:cache` / `route:cache`, если используете кеш (иначе `config/permission.php` может не попасть в бандл).

## Ошибка `Unknown column 'rental_units.tenant_id'`

Таблица `rental_units` создана миграцией **после** той, что добавляла `tenant_id` только «если таблица уже есть» — на многих средах колонка **не создалась**. Исправление: миграция **`2026_03_29_090000_add_tenant_id_to_rental_units_if_missing`** — выполните `php artisan migrate --force` на production.

## Ошибка `config/permission.php not loaded`

```bash
php artisan config:clear
php artisan migrate --force
```

Убедитесь, что `config/permission.php` задеплоен. После успешного migrate: при желании `php artisan config:cache`.

## Document root

Виртуальный хост должен указывать на каталог **`public`** приложения, без `/public/` в URL.

## Кастомные домены (multi-tenant)

**Важно:** переменные `TENANCY_*` должны быть в `.env` **до** `config:cache` и `route:cache` (маршруты читают `config('tenancy.*')` при регистрации). После смены доменов: `php artisan route:clear`, при необходимости `config:clear`, затем снова кеш.

1. `.env`: `TENANCY_CENTRAL_DOMAINS`, `TENANCY_ROOT_DOMAIN`, `TENANCY_SERVER_IP`, при необходимости `TENANCY_PROVISION_SCRIPT`, `TENANCY_LE_WEBROOT`, `TENANCY_PROVISION_USE_SUDO`.
2. Скрипт `scripts/rentbase-provision-domain.sh` на сервере, sudo для очереди при необходимости.
3. Выпуск TLS/nginx — через `ProvisionTenantCustomDomainJob` (очередь).
4. `php artisan tenancy:report-domains` — сводка по доменам.

---

Приёмка безопасности: [security-and-golive.md](security-and-golive.md).
