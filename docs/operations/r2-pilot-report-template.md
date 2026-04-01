# R2 pilot report (template)

Заполняется после пилотного прогона миграции. **Не коммитить** в репозиторий экземпляры с production-идентификаторами, если политика команды это запрещает; хранить копию в тикете / внутренней wiki.

| Поле | Значение |
|------|----------|
| Дата | YYYY-MM-DD |
| Окружение | staging / production / другое |
| Выполнил | |
| Tenant id / cohort | один id, список id или «все активные» |
| Мигрированные сегменты | branding / media / seo (отметить) |

## Команды

Вставить фактически выполненные команды (без секретов):

```
(пример)
php artisan tenant-storage:migrate-to-r2 --dry-run --tenant=...
php artisan tenant-storage:migrate-to-r2 --tenant=... --only=branding
```

## Ссылки на логи

| Лог | Путь / вложение |
|-----|-----------------|
| Dry-run | |
| Branding | |
| Media | |
| SEO | |

## Агрегированные статусы (из JSON stdout)

Подсчитать строки по полю `status` (вручную или `jq`).

| status | count |
|--------|-------|
| `copied` | |
| `db_updated` | |
| `skipped_already_migrated` | |
| `skipped_missing_source` | |
| `skipped_wrong_source_disk` | |
| `error_read` | |
| `error_write` | |
| `dry_run_would_copy` | (только dry-run) |

## Ручная верификация

- [ ] Брендинг на сайте (логотип / favicon / hero) без 404
- [ ] Spatie: превью в Filament
- [ ] Медиа на витрине
- [ ] `/storage/tenants/{id}/public/...` в cloud-режиме → redirect, не 404
- [ ] SEO private snapshots читаются приложением
- [ ] Source-файлы на старых дисках на месте

**Source files still present:** yes / no

## Вывод по rollout

Кратко: успех / частичный успех / откат / повтор требуется.

Можно ли масштабировать на остальных тенантов: да / нет / условно (уточнить).

## Cutover safety

| Вопрос | Ответ |
|--------|--------|
| Global public disk switch ready | yes / no |
| Branding readiness | all active tenants / partial / not ready |
| Blockers | (список или «нет») |

Примечания:

- Для **production** «global public disk switch ready = yes» только если branding скопирован для **всех** активных тенантов и пройден checkpoint из [r2-migration-runbook.md](r2-migration-runbook.md).
- Пилот **одного** tenant с `--only=branding` **не** доказывает безопасность переключения `TENANT_STORAGE_PUBLIC_DISK` для всей площадки.
