# Runbook: `tenant-storage:migrate-to-r2`

Команда копирует объекты по ключам из БД с **source** на **target** диск, опционально обновляет строки (Spatie `media`, `tenant_seo_files`). **v1 никогда не удаляет** файлы на source-диске.

## Команда

```bash
php artisan tenant-storage:migrate-to-r2 --dry-run
php artisan tenant-storage:migrate-to-r2 --tenant=12 --only=branding,media,seo
php artisan help tenant-storage:migrate-to-r2
```

### Полезные флаги

| Флаг | Назначение |
|------|------------|
| `--dry-run` | Только JSON-лог планируемых действий, без записи на target и без обновления БД (кроме сценариев, где явно только лог — см. код). |
| `--tenant=ID` | Один тенант (branding по `tenant_id`; media по связанным мото/отзывам; seo по `tenant_id`). |
| `--only=` | Подмножество: `branding`, `media`, `seo`. |
| `--from-public` / `--to-public` | По умолчанию `public` → `r2-public`. |
| `--from-private` / `--to-private` | По умолчанию `local` → `r2-private`. |

## Формат лога (stdout)

Каждая строка — **один JSON-объект**. Удобно перенаправить в файл и разобрать `jq`.

### Статусы

| status | Смысл |
|--------|--------|
| `dry_run_would_copy` | Dry-run: планируется копирование. |
| `copied` | Файл записан на target. |
| `db_updated` | Обновлена строка БД (media disk / seo `storage_disk`). |
| `skipped_already_migrated` | Уже на target или запись уже указывает на target disk. |
| `skipped_missing_source` | Исходный ключ отсутствует на source-диске — проверить бэкап/путь. |
| `skipped_wrong_source_disk` | (seo) поле `storage_disk` не совпадает с `--from-private` — ручная проверка. |
| `error_read` | Не удалось прочитать stream с source. |
| `error_write` | Не удалось записать на target. |

## Branding vs global public disk

Миграция **branding** (`--only=branding`) **только копирует** объекты на target-диск с **тем же object key**; строки `tenant_settings` **не меняются**. Ключи по-прежнему хранятся в БД как сейчас (`tenants/{id}/public/site/...`).

Чтение брендинга в приложении идёт через **глобальный** `TENANT_STORAGE_PUBLIC_DISK` / `TenantStorageDisks::publicDiskName()` — один диск на всё приложение.

**Почему `--tenant=…` не изолирует branding cutover на production:** после переключения `TENANT_STORAGE_PUBLIC_DISK=r2-public` **все** тенанты начинают читать логотипы/hero/favicon **только** с R2. Тенанты, для которых объекты ещё не скопированы на `r2-public`, получат **404** на брендинг, даже если пилот «успешен» для одного tenant id.

### Два безопасных режима

1. **Staging / изолированное окружение** — один или несколько тенантов, полный cutover env на `r2-*`, пилот команды и smoke без риска для остальной базы клиентов.
2. **Production** — **сначала** волна копирования branding для **всех активных** тенантов (команда **без** `--tenant` или батчами по id с полным покрытием), **затем** один раз переключить `TENANT_STORAGE_PUBLIC_DISK=r2-public` (и согласовать `MEDIA_DISK` для новых загрузок — см. [r2-batch-rollout-plan.md](r2-batch-rollout-plan.md)).

**Media** и **SEO**: обновление диска в БД **построчно** (`media.disk`, `tenant_seo_files.storage_disk`) — ими можно управлять **tenant-by-tenant** или батчами без обязательного одновременного global switch для всего брендинга (Spatie при отображении использует диск записи).

### Checkpoint перед global cutover (public disk)

Не переключать `TENANT_STORAGE_PUBLIC_DISK` на `r2-public`, пока не выполнено:

- [ ] Объекты branding для **всех** активных тенантов скопированы на target (лог без массовых `skipped_missing_source` / `error_*` по branding; повторный прогон даёт в основном `skipped_already_migrated`).
- [ ] Выборочная ручная проверка нескольких сайтов тенантов **на staging** с тем же bucket или spot-check ключей в R2 против `tenant_settings`.
- [ ] Только после этого — switch `TENANT_STORAGE_PUBLIC_DISK` (и связанный smoke из [r2-production-smoke.md](r2-production-smoke.md)).

Шаблон отчёта о пилоте: [r2-pilot-report-template.md](r2-pilot-report-template.md).

## Рекомендуемый порядок на проде

1. Полный `--dry-run` без `--tenant`, сохранить лог.
2. Волна **branding** для **всех** нужных тенантов (не полагаться на пилот одного tenant как на cutover strategy для production branding).
3. **Checkpoint** (см. выше), затем global switch public disk при необходимости.
4. **Media** и **SEO** — по [r2-batch-rollout-plan.md](r2-batch-rollout-plan.md): tenant-by-tenant или малые батчи, сегменты `--only=media`, `--only=seo`.
5. Пилот с `--tenant=` на production имеет смысл для **media/seo** и для отработки процесса; для **branding** на production — только в составе полной волны или на staging.

## Post-migration verification (чеклист)

- [ ] В UI S3/R2 или аналоге объекты видны по ожидаемым ключам (`tenants/.../public/site/...`, `.../public/media/...`, `.../private/...`).
- [ ] Брендинг на публичном сайте открывается; нет битых картинок.
- [ ] Spatie: превью в Filament и на витрине; conversions открываются (тот же диск, что originals).
- [ ] SEO: снимки читаются приложением с private disk (как до миграции).
- [ ] Строки `media.disk` / `tenant_seo_files.storage_disk` соответствуют целевым дискам там, где команда вывела `db_updated`.
- [ ] Source-файлы на старых дисках **на месте** (rollback возможен).

## Rollback

Так как **source не удаляется**:

1. Вернуть env: `TENANT_STORAGE_PUBLIC_DISK`, `TENANT_STORAGE_PRIVATE_DISK`, `MEDIA_DISK`, `SEO_FILES_DISK` на прежние значения (локальные диски).
2. При необходимости **откатить** строки БД, которые были обновлены (`media`, `tenant_seo_files`), из бэкапа БД или вручную по логу `db_updated`.
3. Объекты, уже записанные на R2, **автоматически не удалять** — оставить до ручной уборки, чтобы не потерять данные при двусмысленном состоянии.

## Устранение

- Много `skipped_missing_source`: сверить, что ключ в БД и фактический файл на **том** диске, который указан в `--from-*`.
- `error_read` / `error_write`: права ключей S3/R2, лимиты, сеть; повторить запуск — команда **идемпотентна** (пропускает уже совпадающие по размеру объекты).
