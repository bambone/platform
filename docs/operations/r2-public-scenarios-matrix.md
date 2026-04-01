# R2: матрица публичных сценариев tenant media

Цель: зафиксировать, где данные **записываются**, где **лежит ключ в БД**, какой **диск** и как строится **URL**. Namespace `site/*` и `media/*` **не меняем** на этом этапе.

| Сценарий | Загрузка / запись | Ключ в БД / модель | Диск чтения | URL в рантайме | Local (`public`) | R2 (`r2-public`) |
|----------|-------------------|--------------------|-------------|----------------|------------------|-------------------|
| Логотип / favicon / hero | `Filament` `Settings` → `FileUpload` на `tenant_storage.public_disk` | `tenant_settings` `branding.*_path` = `tenants/{id}/public/site/...` | `TENANT_STORAGE_PUBLIC_DISK` | `tenant_branding_*_url()` → `exists` + `url` / legacy URL | OK | OK (прямой CDN URL или совместимый путь) |
| Тема / публичные ассеты под `tenants/.../public/` | `putPublic` / нормализация | ключ под `public/site/...` | тот же public disk | `tenant_theme_public_url()` | OK | OK |
| Spatie: обложки мото, галереи, отзывы | `MotorcycleResource`, `ReviewResource`, и т.д. | `media` table, `disk` + путь от корня диска | `MEDIA_DISK` (= обычно тот же, что tenant public) | `$media->getUrl()`, conversions на том же диске | OK | OK (URL с диска, не symlink) |
| Legacy avatar в `Review` (без Spatie) | старый импорт | колонка `avatar` = object key или legacy префикс | `TenantStorageDisks::publicDiskName()` | `getAvatarUrlAttribute` | OK | OK |
| Same-origin «красивый» URL | маршрут | — | `TenantPublicStorageFileController` | `/storage/tenants/{id}/public/...` | `response()->file()` | **302** на канонический URL объекта |

## Маршрут `/storage/tenants/{id}/public/{path}`

См. `routes/web.php` и `TenantPublicStorageFileController`: при облачном public-диске ответ — редирект, поэтому закладки и относительные ссылки остаются валидными без локального symlink на объект.

## Что проверить вручную на `r2-public`

1. Загрузить логотип в **Настройки** → файл в bucket, превью в админке открывается.
2. Загрузить медиа мото / аватар отзыва → превью Filament и публичный сайт.
3. Открыть в браузере `/storage/tenants/{id}/public/site/logo/...` → **302** на публичный URL, не 404.
4. Убедиться, что в БД по-прежнему **только ключи**, не полные URL.

Связанные документы: [r2-tenant-storage.md](r2-tenant-storage.md), [r2-production-smoke.md](r2-production-smoke.md).
