# Tenant storage: локальный public disk и Cloudflare R2

Локальное зеркало, dual-write и delivery `/media/…`: см. [tenant-media-local-mirror.md](tenant-media-local-mirror.md).

### Запись и синхронизация с R2

- **Нормальный путь записи** — `TenantStorage::putPublic` / `putInArea` → `TenantPublicMediaWriter`: сначала зеркало (`tenant_storage.public_mirror_disk`), при режиме `dual` — PUT в `r2-public` или строка в `media_replication_outbox` + job `ProcessMediaReplicationOutboxJob` (раз в минуту в `routes/console.php`).
- **Догонка без удалений** между зеркалом и R2: `php artisan tenant-storage:sync-replica` (`TenantStorageSyncReplicaCommand`).
- Загрузки **page builder** (модалка файлов, editorial gallery и др.) должны использовать тот же writer. Прямой `Storage::disk(TENANT_STORAGE_PUBLIC_DISK)->put*()` при диске-зеркале **обходит** реплику в R2 — на проде файлы могут остаться только на ФС сервера, а в бакете их не будет (backfill с пустым префиксом).

## Два трека (не смешивать в одном PR)

1. **Dual-disk read/write** — приложение корректно работает с `public` или `r2-public` / `local` или `r2-private` без смены ключей в БД.
2. **Нормализация namespace** — **конечная цель** схемы (`public/branding`, `public/catalog`, `private/crm`, …) не отменяется; переносится на **поздний этап** после стабильного dual-disk rollout (`site/*` → `branding/*` и т.д.), отдельная команда и PR, без смешивания с включением R2.

## Диски


| Назначение                                                  | Конфиг / env                                                                       | Типичные значения                                                |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| Публичные tenant-файлы (брендинг, медиа на публичном диске) | `tenant_storage.public_disk` → `TENANT_STORAGE_PUBLIC_DISK`                        | `public`, `r2-public`                                            |
| Приватные (SEO snapshots, приватные аплоады)                | `tenant_storage.private_disk` → `TENANT_STORAGE_PRIVATE_DISK` или `SEO_FILES_DISK` | `local`, `r2-private`                                            |
| Spatie Media Library                                        | `media-library.disk_name` → `MEDIA_DISK`                                           | тот же публичный диск, что и tenant public, если медиа публичные |


Хелпер в коде: `App\Support\Storage\TenantStorageDisks`.

## Формат значений в БД (object key)

Для файлов в bucket хранится **только ключ относительно корня диска**, например:

`tenants/12/public/site/logo/logo.png`

Нельзя хранить:

- полный URL (`https://…`)
- абсолютный путь ФС
- несогласованный префикс `storage/…` вместо ключа

Публичный URL строится в рантайме: `TenantStorage::publicUrl()`, дисковый `url()` для публичного диска, или signed/temporary URL для приватного (отдельный API).

### Прямые CDN URL в HTML (публичный сайт тенанта)

Когда задан `**TENANT_STORAGE_PUBLIC_CDN_URL`** (`config('tenant_storage.public_cdn_base_url')`) и публичный диск **не** локальный Flysystem, `TenantPublicAssetResolver` в HTTP-контексте отдаёт **прямой** URL вида `{CDN}/tenants/{id}/public/{path}` (как `TenantStorage::publicUrl()`), без прокси через `/storage/...` на origin. Для локального `public` диска в разработке по-прежнему используется маршрут `/storage/tenants/...`.

Значение CDN должно указывать на тот же namespace объектов, что и ключи в bucket (`tenants/*/public/*`), обычно совпадает с публичным origin R2 или custom domain в Cloudflare.

### Следующий этап: варианты размеров изображений

Чтобы снизить байты на LCP и карточках, отдельно планируются несколько размеров одного смысла (hero desktop/mobile, card, thumb) и разметка `srcset`/`sizes` или разные URL по breakpoint — после стабилизации прямой выдачи через CDN.

### Видео на первом экране

Не использовать тяжёлое видео как основной LCP-элемент: постер, открытие по действию пользователя (modal), умеренный `preload` у плеера в диалоге (`metadata` вместо полной предзагрузки), без агрессивного autoplay на mobile.

## Инварианты для приватного диска (`r2-private`)

- Не использовать общий public-layer (`url()` на приватном bucket для отдачи в браузер).
- Не хранить в БД прямые публичные ссылки на объекты приватного bucket.
- Доступ: backend + policy, **signed URL** или **temporary URL** — явный контракт, не через `TenantStorage::publicUrl()` для приватных ключей.

## Отдача через маршрут `/storage/tenants/{id}/public/…`

- **Локальный** публичный диск: ответ `response()->file()` (как раньше).
- **Облачный** публичный диск: **HTTP 302** на канонический URL объекта (CDN/R2 public URL). Стриминг через PHP только при `**TENANT_STORAGE_STREAM_PUBLIC_THROUGH_ORIGIN=true`** (legacy, неверный `Content-Type` на объекте).

### Env (публичные ассеты)


| Переменная                                     | Назначение                                                                                                                                                                                                                                            |
| ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `TENANT_STORAGE_PUBLIC_CDN_URL`                | База для прямых URL в разметке при облачном public disk (без завершающего `/`).                                                                                                                                                                       |
| `R2_PUBLIC_URL`                                | Публичный URL диска `r2-public` в `config/filesystems.php`; должен быть согласован с CDN/custom domain для того же bucket.                                                                                                                            |
| `TENANT_STORAGE_STREAM_PUBLIC_THROUGH_ORIGIN`  | `false` по умолчанию; `true` — стрим изображений/видео через Laravel вместо 302.                                                                                                                                                                      |
| `TENANT_STORAGE_PUBLIC_OBJECT_CACHE_CONTROL`   | Заголовок для **новых** объектов на S3/R2 при `put` (`public, max-age=31536000, immutable` по умолчанию). Нужен, чтобы Cloudflare и браузер кешировали ответы; **уже залитые** файлы не меняются сами — перезалить или поправить метаданные в bucket. |
| `TENANT_STORAGE_PUBLIC_REDIRECT_CACHE_CONTROL` | `Cache-Control` для HTTP **302** с маршрута `/storage/...` на CDN (по умолчанию сутки), чтобы реже бить Laravel.                                                                                                                                      |


### Почему «кеш на Laravel» не ускоряет картинки с CDN

Запрос браузера к `https://cdn…/tenants/…/public/…` **не проходит** через ваш PHP-сервер. Кешировать на origin имеет смысл только если трафик идёт через прокси на приложении. Для скорости важны: **заголовки на объекте** (см. env выше), **правила Cloudflare** (кешировать `tenants/*/public/`*, respect origin / override TTL), и **размер файлов** (`srcset`, WebP). В DevTools у ответа смотрите `cf-cache-status: HIT` после первого запроса.

## Первый rollout

Существующие ключи `tenants/.../public/site/...` и `.../public/media/...` остаются валидными; миграция в R2 — отдельная команда с `--dry-run`, без удаления source в v1.

## Команда миграции (после dual-disk)

```bash
php artisan tenant-storage:migrate-to-r2 --dry-run
php artisan tenant-storage:migrate-to-r2 --tenant=12 --only=branding,media,seo
```

Флаги: `--from-public`, `--to-public`, `--from-private`, `--to-private` (см. `php artisan tenant-storage:migrate-to-r2 --help`). Лог — JSON-строки в stdout (`status`, диски, ключи, id строки). Повторный запуск идемпотентен; v1 **не удаляет** файлы на source-диске.

## Целевая namespace-схема (PR6+, не смешивать с rollout)

Конечная цель (`public/branding`, `public/catalog`, `private/crm`, …) выполняется **отдельно** после стабильной работы на R2; см. план rollout в репозитории.

## Инвентаризация приватных аплоадов (CRM / лиды)

Текущий код CRM в основном не пишет в `Storage` напрямую; при появлении вложений — только `r2-private`, signed URL, ключи в БД по правилам выше.

## Документы следующего этапа (прикладное внедрение)


| Документ                                                       | Содержание                                                                            |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| [r2-public-scenarios-matrix.md](r2-public-scenarios-matrix.md) | Матрица публичных сценариев (брендинг, Spatie, маршрут `/storage/...`).               |
| [r2-private-tenant-files.md](r2-private-tenant-files.md)       | Контракт приватных файлов, `temporaryPrivateUrl`, будущие префиксы CRM.               |
| [r2-migration-runbook.md](r2-migration-runbook.md)             | Запуск миграции, статусы JSON-лога, verification, rollback, branding vs global disk.  |
| [r2-batch-rollout-plan.md](r2-batch-rollout-plan.md)           | Волны mass rollout (production vs staging), порядок branding → cutover → media → seo. |
| [r2-pilot-report-template.md](r2-pilot-report-template.md)     | Шаблон отчёта о пилоте (без production id в git).                                     |
| [r2-production-smoke.md](r2-production-smoke.md)               | Smoke-чеклист и согласованность env после включения R2.                               |


