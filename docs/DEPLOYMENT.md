# Деплой на production

## Tenant branding (загрузки в storage)

Файлы брендинга клиента пишутся в `storage/app/public/tenants/{tenant_id}/…`. На сервере должен существовать симлинк:

```bash
php artisan storage:link
```

Без него браузер не отдаст `/storage/...` (404). После смены `APP_URL` пересоберите кеш конфига, чтобы `Storage::url()` совпадал с публичным origin.

Опционально: `TENANCY_LOG_VIEW_RESOLUTION=true` в `.env` для отладки выбора Blade-темы (пишет в `debug`-лог); на проде обычно `false`.

---

<a id="filament-storage-cache-permissions"></a>

## Filament: `Permission denied` в `storage/framework/cache` (все разделы админки)

**Симптом в логе:**  
`file_put_contents(.../storage/framework/cache/data/...): Failed to open stream: Permission denied`  
часто при рендере `filament/tables` или после цепочки **Spatie Permission** (`PermissionRegistrar` пишет кеш прав).

**Причина:** процесс PHP (обычно пользователь **`www-data`**, **`nginx`**, **`apache`** или отдельный пользователь пула PHP-FPM) **не имеет права записи** в каталоги `storage/` и/или `bootstrap/cache/`. Так бывает после деплоя от **root**, если `artisan` или `composer` создали файлы с владельцем root.

**Что сделать на сервере** (путь замените на свой, например `/var/www/platform`):

1. Узнать пользователя PHP-FPM (часто `www-data`):

   ```bash
   grep -E '^user|^group' /etc/php/*/fpm/pool.d/www.conf 2>/dev/null | head -4
   ```

2. Выдать владельца и права на запись (пример для `www-data`):

   ```bash
   cd /var/www/platform
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
   sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
   ```

3. Сбросить кеш приложения **от того же пользователя**, что обслуживает сайт (или после `chown`):

   ```bash
   sudo -u www-data php artisan cache:clear
   sudo -u www-data php artisan config:clear
   ```

4. Не запускайте `php artisan ...` от **root** в каталоге проекта без последующего `chown` — иначе снова появятся root-владельцы и ошибка вернётся.

**Альтернатива (инфраструктура):** вынести кеш приложения с диска, задав в `.env` например `CACHE_STORE=database` или `redis` (при настроенном Redis/таблице cache). Это **не отменяет** необходимости писать в `storage` для логов, сессий (если `file`), скомпилированных views, загрузок — права на `storage/` всё равно нужны.

---

## Ошибка `Table '…tenant_domains' doesn't exist`

Сообщение `SQLSTATE[42S02]: Base table or view not found: 1146 … tenant_domains` означает, что в **той БД**, к которой подключается Laravel на сервере (см. `DB_*` в `.env`), **ещё не создана схема** из миграций. Локально таблица есть, потому что у вас выполнен `php artisan migrate`.

### Что сделать на сервере

1. SSH в хостинг, перейти в каталог приложения (у вас в логе: `…/gmtst2.ru/motolevins`).
2. Убедиться, что `.env` указывает на нужную базу `xnjlcdaa_motolevins` (или актуальное имя).
3. Выполнить миграции в неинтерактивном режиме:

```bash
php artisan migrate --force
```

4. Проверить, что миграция `2026_03_19_090002_create_tenant_domains_table` в статусе **Ran**:

```bash
php artisan migrate:status
```

5. **Порядок важен:** сначала миграции, потом кеш конфига. Если выполнить `php artisan config:cache` до появления на сервере всех файлов в `config/` (в т.ч. `config/permission.php`), в `bootstrap/cache/config.php` не попадёт пакетный конфиг — следующий `migrate` упрётся в ошибку ниже.

6. После успешных миграций при необходимости: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.

После этого запрос к `tenant_domains` в `TenantResolver` перестанет падать с 1146.

---

## Ошибка `config/permission.php not loaded` (миграция `create_permission_tables`)

Текст вроде `Error: config/permission.php not loaded. Run [php artisan config:clear] and try again` (в стеке может фигурировать `helpers.php` из `vendor/laravel/framework` — это внутренняя реализация `throw_if`) выбрасывается из миграции `2026_03_19_125704_create_permission_tables`, когда `config('permission.table_names')` пустой.

**Типичные причины на production**

1. **Устаревший кеш конфига** — ранее запускали `config:cache`, когда файла `config/permission.php` ещё не было или он не попал в деплой.
2. **Файл не задеплоен** — на сервере нет `config/permission.php` (проверьте `ls config/permission.php` или аналог).

**Что сделать**

1. Убедиться, что в репозитории и на сервере есть `config/permission.php`.
2. Сбросить кеш конфигурации и снова прогнать миграции (прерванная миграция прав не создала таблиц Spatie — повторный запуск безопасен):

```bash
php artisan config:clear
php artisan migrate --force
```

3. Уже **после** успешного `migrate` при желании: `php artisan config:cache`.

Так вы избежите ситуации, когда `migrate` читает «пустой» `permission.*` из старого `bootstrap/cache/config.php`.

### Рекомендация по URL

Желательно настроить **document root** виртуального хоста на каталог `public` приложения, чтобы сайт открывался как `https://gmtst2.ru/` без сегмента `/motolevins/public/` в пути.

---

## Кастомные домены (multi-tenant)

**Кеш маршрутов и конфиг vs `TENANCY_*`:** список `TENANCY_CENTRAL_DOMAINS` и остальные переменные `TENANCY_*` должны быть **уже заданы в `.env` (или в окружении)** до выполнения `php artisan config:cache` и **`php artisan route:cache`**, потому что `routes/web.php` читает `config('tenancy.*')` в момент регистрации маршрутов. Если вы поменяли central domains после кеширования, выполните: `php artisan route:clear`, при необходимости `php artisan config:clear` (или заново `config:cache`), затем снова `route:cache`. Иначе маркетинговые `Route::domain(...)` останутся привязаны к старым хостам.

1. В `.env` задайте `TENANCY_*` (см. `.env.example`): `TENANCY_CENTRAL_DOMAINS`, `TENANCY_ROOT_DOMAIN`, `TENANCY_SERVER_IP`, при необходимости `TENANCY_PROVISION_SCRIPT`, `TENANCY_LE_WEBROOT`, `TENANCY_PROVISION_USE_SUDO`.
2. Скопируйте `scripts/rentbase-provision-domain.sh` на сервер в `/usr/local/bin/rentbase-provision-domain`, `chmod 750`, владелец `root`. Подставьте при необходимости `APP_ROOT`, `PHP_SOCK`, `CERTBOT_EMAIL`.
3. Для очереди под пользователем `www-data` ограниченный sudo на один скрипт (пример):  
   `www-data ALL=(root) NOPASSWD: /usr/local/bin/rentbase-provision-domain`  
   и `TENANCY_PROVISION_USE_SUDO=true`.
4. Выпуск сертификата и правка nginx выполняются **только** через `ProvisionTenantCustomDomainJob` (очередь), не из HTTP-запроса.
5. Перед релизом: `php artisan tenancy:report-domains` — сводка по `status` / `ssl_status` в `tenant_domains`.
6. **Backlog:** сценарий отключения кастомного домена (снять vhost, опционально revoke cert) — пока вручную; см. `scripts/rentbase-provision-domain.sh` как зеркало для скрипта отключения.
