# Tenant storage: локальный public disk и Cloudflare R2

## Два трека (не смешивать в одном PR)

1. **Dual-disk read/write** — приложение корректно работает с `public` или `r2-public` / `local` или `r2-private` без смены ключей в БД.
2. **Нормализация namespace** — **конечная цель** схемы (`public/branding`, `public/catalog`, `private/crm`, …) не отменяется; переносится на **поздний этап** после стабильного dual-disk rollout (`site/*` → `branding/*` и т.д.), отдельная команда и PR, без смешивания с включением R2.

## Диски

| Назначение | Конфиг / env | Типичные значения |
|------------|----------------|-------------------|
| Публичные tenant-файлы (брендинг, медиа на публичном диске) | `tenant_storage.public_disk` → `TENANT_STORAGE_PUBLIC_DISK` | `public`, `r2-public` |
| Приватные (SEO snapshots, приватные аплоады) | `tenant_storage.private_disk` → `TENANT_STORAGE_PRIVATE_DISK` или `SEO_FILES_DISK` | `local`, `r2-private` |
| Spatie Media Library | `media-library.disk_name` → `MEDIA_DISK` | тот же публичный диск, что и tenant public, если медиа публичные |

Хелпер в коде: `App\Support\Storage\TenantStorageDisks`.

## Формат значений в БД (object key)

Для файлов в bucket хранится **только ключ относительно корня диска**, например:

`tenants/12/public/site/logo/logo.png`

Нельзя хранить:

- полный URL (`https://…`)
- абсолютный путь ФС
- несогласованный префикс `storage/…` вместо ключа

Публичный URL строится в рантайме: `TenantStorage::publicUrl()`, дисковый `url()` для публичного диска, или signed/temporary URL для приватного (отдельный API).

## Инварианты для приватного диска (`r2-private`)

- Не использовать общий public-layer (`url()` на приватном bucket для отдачи в браузер).
- Не хранить в БД прямые публичные ссылки на объекты приватного bucket.
- Доступ: backend + policy, **signed URL** или **temporary URL** — явный контракт, не через `TenantStorage::publicUrl()` для приватных ключей.

## Отдача через маршрут `/storage/tenants/{id}/public/…`

- **Локальный** публичный диск: ответ `response()->file()` (как раньше).
- **Облачный** публичный диск: **HTTP 302** на канонический URL объекта (CDN/R2 public URL), без стриминга через PHP по умолчанию.

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

| Документ | Содержание |
|----------|------------|
| [r2-public-scenarios-matrix.md](r2-public-scenarios-matrix.md) | Матрица публичных сценариев (брендинг, Spatie, маршрут `/storage/...`). |
| [r2-private-tenant-files.md](r2-private-tenant-files.md) | Контракт приватных файлов, `temporaryPrivateUrl`, будущие префиксы CRM. |
| [r2-migration-runbook.md](r2-migration-runbook.md) | Запуск миграции, статусы JSON-лога, verification, rollback, branding vs global disk. |
| [r2-batch-rollout-plan.md](r2-batch-rollout-plan.md) | Волны mass rollout (production vs staging), порядок branding → cutover → media → seo. |
| [r2-pilot-report-template.md](r2-pilot-report-template.md) | Шаблон отчёта о пилоте (без production id в git). |
| [r2-production-smoke.md](r2-production-smoke.md) | Smoke-чеклист и согласованность env после включения R2. |
