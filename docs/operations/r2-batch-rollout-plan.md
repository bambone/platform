# R2 batch rollout plan

Безопасный порядок массового внедрения после dual-disk и команды `tenant-storage:migrate-to-r2`. **PR6 / смена `site/*` и `media/*` не входят.** **v1 команды не удаляет source.**

## Ключевые различия сегментов

| Сегмент | Обновление БД | Cutover |
|---------|---------------|---------|
| **Branding** | Нет (только копия файла с тем же key) | **Глобально чувствителен:** `TENANT_STORAGE_PUBLIC_DISK` один на приложение |
| **Media (Spatie)** | Да (`media.disk`, при необходимости `conversions_disk`) | Построчно; можно tenant-by-tenant |
| **SEO** | Да (`tenant_seo_files.storage_disk`) | Построчно / по tenant |

Команда **идемпотентна**; логи **обязательно сохранять** (файл + тикет).

---

## Production strategy

### 1. Readiness и бэкапы

- Согласовать env: [r2-production-smoke.md](r2-production-smoke.md), `.env.example`
- Бэкап БД (и при необходимости снимок локальных дисков)
- `php artisan tenant-storage:migrate-to-r2 --dry-run` (полный или батч), сохранить JSON-лог

### 2. Branding copy wave (до global public cutover)

- Выполнить копирование branding для **всех активных** тенантов: команда **без** `--tenant` или явные батчи по id с **полным** покрытием списка активных клиентов
- Убедиться, что нет необработанных критичных `error_*` / массовых `skipped_missing_source` без плана
- **Не** переключать `TENANT_STORAGE_PUBLIC_DISK=r2-public` до завершения этой волны и checkpoint

### 3. Global public disk switch

- Checkpoint «Branding vs global public disk» в [r2-migration-runbook.md](r2-migration-runbook.md)
- Переключить `TENANT_STORAGE_PUBLIC_DISK` (и выровнять `MEDIA_DISK` для **новых** загрузок с публичным смыслом — обычно тот же `r2-public`)
- Smoke: брендинг, один Spatie объект, маршрут `/storage/tenants/...`

### 4. Media rollout

- Tenant-by-tenant или малые батчи: `--only=media` (с `--tenant=` или скриптом по списку)
- После волны — выборочная проверка Filament и витрины

### 5. SEO rollout

- `--only=seo`; учитывать `--from-private` / `--to-private` и поле `storage_disk` в строках
- Проверка генерации/чтения снимков на private disk

### 6. Post-wave verification

- Чеклист из [r2-migration-runbook.md](r2-migration-runbook.md#post-migration-verification-чеклист)
- Сверка логов: отсутствие непроработанных ошибок

### 7. Rollback

- См. [r2-migration-runbook.md](r2-migration-runbook.md#rollback); source не удалялся — откат env и при необходимости строк БД для media/seo

### 8. Инвариант v1

- **Не удалять** объекты на source-диске автоматически; уборка — отдельное решение после верификации

---

## Staging / isolated pilot strategy

- Окружение с ограниченным числом тенантов (или копия данных)
- Можно полный cutover `r2-*` для проверки end-to-end
- Пилот с `--tenant=` отрабатывает процедуру и шаблон [r2-pilot-report-template.md](r2-pilot-report-template.md)
- Ошибочно считать такой пилот доказательством безопасности **global** branding switch на production без волны для всех активных тенантов

---

## Ссылки

- Команда и статусы лога: [r2-migration-runbook.md](r2-migration-runbook.md)
- Smoke: [r2-production-smoke.md](r2-production-smoke.md)
- Публичные сценарии: [r2-public-scenarios-matrix.md](r2-public-scenarios-matrix.md)
