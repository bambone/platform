# Tenant public media: локальное зеркало + R2 (write / delivery)

## Инварианты

- В БД хранится только **object key** (`tenants/{id}/public/...`), не полный URL.
- **Write mode**: `local_only` | `r2_only` | `dual`. **Delivery mode**: `local` | `r2`.
- Публичная отдача по HTTP ограничена **только** ключами `tenants/{id}/public/...` (не открывать `healthchecks/` и др. через `/media/`).
- При `delivery=local` **нет** скрытого fallback на R2: нет файла на зеркале → 404; восстановление — backfill / verify.
- `MEDIA_LOCAL_ROOT` задаётся **только** в env на сервере; в Platform UI — лишь подсказка.

## Env (см. `.env.example`)

- `MEDIA_LOCAL_ROOT` — абсолютный путь корня зеркала (на проде вне репозитория).
- `TENANT_STORAGE_PUBLIC_DISK` — для зеркала: `tenant-public-mirror` (или оставить `r2-only` legacy).
- `TENANT_MEDIA_WRITE_MODE_DEFAULT`, `TENANT_MEDIA_DELIVERY_MODE_DEFAULT`.
- `TENANT_MEDIA_LOCAL_PUBLIC_BASE_PATH` — префикс URL (по умолчанию `/media`).
- `TENANT_MEDIA_R2_PUBLIC_BASE_URL` — опционально, база для delivery=r2.
- Креды R2: как для диска `r2-public` (`R2_PUBLIC_BUCKET`, `AWS_ENDPOINT`, …).

## Nginx

Публичный префикс (пример; **не** вешать весь корень бакета, если там служебные префиксы):

```nginx
location /media/ {
    alias /srv/rentbase-media/tenants/;
    try_files /$uri =404;
}
```

Вариант с отдельным поддеревом только `tenants/*/public/*` — см. инфраструктуру. Для переиспользуемых имён файлов **не** использовать `immutable`; умеренный `max-age` + `TENANT_STORAGE_PUBLIC_URL_VERSION`.

Упрощённый вариант (если в зеркале только `tenants/`):

```nginx
location /media/ {
    alias /srv/rentbase-media/;
    try_files $uri =404;
    access_log off;
    add_header Cache-Control "public, max-age=86400";
}
```

URL в приложении: `/media/tenants/{id}/public/...`.

## GitHub Actions (`media_backfill` в `ci.yml`)

- Переменная репозитория **`MEDIA_BACKFILL_AS_WWW_DATA`**: если не задана или пустая, backfill запускается **от SSH-пользователя** (без `sudo`). Значение **`1`** — выполнять `sudo -n -u www-data` (нужен passwordless sudo для этого пользователя на сервере).
- Убедитесь, что SSH-пользователь может писать в `MEDIA_LOCAL_ROOT` и читать/писать каталог приложения (или оставьте `1` и настройте sudoers).
- **Код выхода 1** у `tenant-media:backfill-from-r2`: неверный/пустой `--target`, нет/бит `r2-public` (в т.ч. `R2_PUBLIC_BUCKET`), сбой `ListObjectsV2`, или **хотя бы один объект** не скачался (`failed > 0` в итоговой строке). Лог на сервере: `storage/logs/tenant-media-backfill.log`. В логе Actions при падении печатается хвост этого файла.
- **`mkdir(): Permission denied`** раньше мог **прерывать** весь забег при первом объектe с новым вложенным путём. Сейчас такие ошибки учитываются как `failed` у конкретного ключа и идут в итог `Processed … failed N`; перед обходом S3 добавлена проверка записи корня `--target` с подсказкой про `www-data` / **`MEDIA_BACKFILL_AS_WWW_DATA`**. Если корень недоступен пользователю PHP (SSH deploy при отсутствии ACL), настройте владение/ACL на `MEDIA_LOCAL_ROOT` или задайте **`MEDIA_BACKFILL_AS_WWW_DATA=1`** и passwordless `sudo -u www-data`.

## Rollout

1. Подготовить каталог зеркала и `MEDIA_LOCAL_ROOT`.
2. `php artisan tenant-media:backfill-from-r2 --target=...` (S3 API, не HTTP CDN).
3. Залить зеркало на сервер.
4. Настроить nginx `/media/`.
5. Деплой: `write=dual`, **delivery оставить `r2`**, переключить диск на зеркало при необходимости.
6. Проверить новые загрузки и outbox; `php artisan tenant-media:verify-local-against-r2 --target=...`.
7. Включить `delivery=local` в настройках платформы.

## Команды

- `php artisan tenant-media:backfill-from-r2 --target=ABSOLUTE_PATH` — обязательный путь **вне** git working tree.
- `php artisan tenant-media:verify-local-against-r2 --target=...` — сверка с R2.

Опции backfill: `--dry-run`, `--only-missing`, `--prefix=`, `--tenant=`, `--limit=`, `--manifest=csv|json|both`, `--skip-existing`.

## Dev

На `APP_ENV=local` зарегистрирован fallback-маршрут `GET /media/{path}` (`tenants/{id}/public/...`) к файлам на диске зеркала, если nginx нет.

## Связанные файлы

- `App\Support\Storage\TenantPublicMediaWriter`, `TenantPublicAssetResolver`, `EffectiveTenantMediaModeResolver`
- `media_replication_outbox`, job `ProcessMediaReplicationOutboxJob`
- Док по R2: [r2-tenant-storage.md](r2-tenant-storage.md)
