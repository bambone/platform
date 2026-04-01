# R2: контракт приватных tenant-файлов (future CRM / leads / exports)

**Не смешивать** с публичным слоем. Нормализация namespace (`private/crm`, `private/leads`, …) — **позже** (PR6+); сейчас задача — единые правила и API.

## Что уже есть

| Поток | Реализация | Диск |
|-------|------------|------|
| SEO snapshots / backups | `SeoFileStorage`, `TenantStorageArea::PrivateSeo` | `tenant_storage.private_disk` (часто `SEO_FILES_DISK` / `r2-private`) |
| Прочие приватные записи тенанта | `TenantStorage::putPrivate`, `putPrivateAtomic`, `getPrivate`, `existsPrivate` | тот же private disk |

## Правила (жёстко)

1. В БД хранить только **object key** относительно корня private-диска (как для public), **не** полный URL и **не** публичные ссылки на private bucket.
2. **Не** вызывать `TenantStorage::publicUrl()` и **не** вызывать `$disk->url()` для ключей на private bucket в UI или письмах без явного решения безопасности.
3. Отдача в браузер / внешний клиент: **backend + policy**, либо **pre-signed temporary URL** через единый метод.

## Кодовый контракт

- Запись: `TenantStorage::for($tenant)` или `forCurrent()` → `putPrivate($path, ...)` / `putPrivateAtomic(...)` / `putInArea` для приватных `TenantStorageArea` (при расширении enum позже).
- Чтение содержимого в PHP: `getPrivate`, `existsPrivate`.
- Временная выдача наружу (S3-compatible private bucket):  
  `TenantStorage::for(...)->temporaryPrivateUrl($pathUnderPrivateSegment, $expiration)`  
  где путь — тот же логический хвост, что для `getPrivate` (например `site/seo/robots.txt` после `tenants/{id}/private/`).
- На **локальном** private-диске `temporaryPrivateUrl()` **бросает** `LogicException`: нужен отдельный **авторизованный** route/controller, который читает файл с диска и отдаёт response (policy).

## Будущие домены (пока без массовой реализации)

| Область | Планируемый префикс (PR6+) | Сейчас |
|---------|----------------------------|--------|
| CRM вложения | `tenants/{id}/private/crm/...` | не писать хаотично в `public` |
| Lead uploads | `tenants/{id}/private/leads/...` | — |
| Документы клиента | `tenants/{id}/private/documents/...` | — |
| Экспорты | `tenants/{id}/private/exports/...` | — |

## Отделить от framework / temp

- `storage/framework`, `livewire-tmp`, очереди, кеш — **не** tenant private business storage; не смешивать с `TenantStorage` для данных клиента.

См. также: [r2-tenant-storage.md](r2-tenant-storage.md).
