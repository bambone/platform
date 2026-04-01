# R2 production smoke checklist

Выполнить после переключения env на `r2-public` / `r2-private` (или на staging с теми же значениями).

## Конфиг / env

Убедиться, что согласованы (см. `.env.example`):

- `TENANT_STORAGE_PUBLIC_DISK` — обычно `r2-public` на проде.
- `TENANT_STORAGE_PRIVATE_DISK` или `SEO_FILES_DISK` — обычно `r2-private`.
- `MEDIA_DISK` — **тот же** смысл, что публичные медиа тенанта (`r2-public`), чтобы conversions не остались на другом диске.
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_ENDPOINT`, `AWS_DEFAULT_REGION`, `AWS_USE_PATH_STYLE_ENDPOINT`.
- `R2_PUBLIC_BUCKET`, `R2_PRIVATE_BUCKET`, `R2_PUBLIC_URL` (публичный CDN/домен для объектов public bucket).

## Smoke-сценарии

- [ ] **Публичная загрузка брендинга**: в кабинете тенанта загрузить логотип → объект в public bucket, превью в админке OK.
- [ ] **Публичный URL**: открыть сайт тенанта — логотип/hero без 404.
- [ ] **Маршрут `/storage/tenants/{id}/public/...`**: ответ **302** на CDN URL (не стрим PHP), для существующего ключа.
- [ ] **Spatie**: одно медиа (мото или отзыв) — загрузка, превью в Filament, отображение на сайте.
- [ ] **Миграция dry-run**: `php artisan tenant-storage:migrate-to-r2 --dry-run --tenant=<pilot>` — без ошибок, осмысленные статусы в JSON.
- [ ] **Миграция пилот**: один изолированный тенант без `--dry-run` (после бэкапа) — затем verification из [r2-migration-runbook.md](r2-migration-runbook.md).
- [ ] **Приватный SEO**: генерация/чтение снимков (robots/sitemap) с private disk без появления публичных URL в БД.

## Guardrails для разработки

- Для путей вида `tenants/{id}/public/...` предпочитать **`TenantStorage`**, `TenantStorageDisks::publicDiskName()`, хелперы брендинга — а не жёсткий `Storage::disk('public')`.
- Новые фичи с вложениями CRM/leads — только [r2-private-tenant-files.md](r2-private-tenant-files.md); не публиковать private bucket через общий `publicUrl`.
