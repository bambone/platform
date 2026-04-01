# R2 primary tenant storage — execution checklist

Операторский порядок без лишней теории. Детали смоков: [r2-production-smoke.md](r2-production-smoke.md). Контекст: [r2-tenant-storage.md](r2-tenant-storage.md).

## Кто что делает

| Где | Действия |
|-----|----------|
| **Репозиторий / CI** | Merge кода, `php artisan test`, ревью чеклиста. |
| **Сервер (staging/prod)** с реальной БД, `.env` и bucket’ами | Все команды `tenant-storage:migrate-to-r2`, правка `.env`, `config:cache`, рестарт php-fpm/workers, `tee` логов, ручная проверка объектов в R2 и смоки в браузере. |

Ассистент/локальная машина без ваших секретов и прод-БД **не заменяет** шаги из колонки «Сервер».

## Локально сначала, прод потом

1. Отработать §0–7 на **локальной** машине (та же логика `.env`, что и в бою: `r2-public` / `r2-private` и четыре переменные tenant/media/SEO). Bucket’ы можно те же или отдельные dev — по политике команды.
2. Ротацию паролей / R2 API token сделать **после** того, как локально всё заведено и проверено end-to-end (как договорились).
3. **Прод** включать, когда локальный сценарий зелёный: скопировать блок из `.env.example`, без экспериментов на бою раньше времени.

Если в `.env` уже стоит `TENANT_STORAGE_PUBLIC_DISK=r2-public`, а объекты ещё лежат на локальном `public`, миграция копирует **по флагам**, не по tenant env:  
`--from-public=public --to-public=r2-public` (и аналогично для private: `--from-private=local --to-private=r2-private`).

## 0. Freeze перед rollout

- Не мержить новые фичи в storage/media на время окна.
- Не менять `.env` **в процессе** шагов миграции (только на чётких cutover-шагах ниже).
- Очередь стабильна (нет залипших job’ов, которые массово пишут в старый диск).

## 1. Dry-run branding

Сохранить stdout в артефакт (bash). При уже включённом R2 в `.env` добавь `--from-public=public --to-public=r2-public`.

```bash
php artisan tenant-storage:migrate-to-r2 --dry-run --only=branding \
  --from-public=public --to-public=r2-public \
  2>&1 | tee branding-dry-run-$(date +%F).log
```

Проверить: нет массовых `error_*`; `skipped_missing_source` понятны и ожидаемы.

## 2. Branding full wave

Пилот одного `--tenant` **недостаточен** перед глобальным public cutover — нужен прогон **без** `--tenant`.

```bash
php artisan tenant-storage:migrate-to-r2 --only=branding \
  --from-public=public --to-public=r2-public \
  2>&1 | tee branding-wave-$(date +%F).log
```

Идемпотентность — второй прогон:

```bash
php artisan tenant-storage:migrate-to-r2 --only=branding \
  --from-public=public --to-public=r2-public
```

Ожидание: почти всё `skipped_already_migrated`.

## 3. Checkpoint перед public cutover (не пропускать)

- В логах нет массовых `error_write` / массовых неожиданных `skipped_missing_source`.
- Вручную: 2–3 tenant’а — объекты в R2 public bucket, logo/hero реально на месте.

Если ок — дальше cutover. Логи dry-run и branding-wave хранить как доказательство readiness.

## 4. Public cutover

`.env`:

```env
TENANT_STORAGE_PUBLIC_DISK=r2-public
MEDIA_DISK=r2-public
```

Далее:

```bash
php artisan config:clear
php artisan config:cache
```

Рестарт: php-fpm (или эквивалент), queue workers.

## 5. Smoke сразу после public cutover

- Сайт: логотип, hero, favicon — без пустых мест и без 404 там, где раньше были файлы.
- URL вида `/storage/tenants/{id}/public/site/logo/...` → **302** на CDN (cloud public disk).
- Filament: превью брендинга видны.

Если это прошло — самый рискованный шаг позади. Подробнее: [r2-production-smoke.md](r2-production-smoke.md).

## 6. Media rollout

Полный лог (пример):

```bash
php artisan tenant-storage:migrate-to-r2 --only=media \
  --from-public=public --to-public=r2-public \
  2>&1 | tee media-wave-$(date +%F).log
```

Или батчами: `--tenant=1`, `--tenant=2`, …

Проверки: Filament, витрина, conversions. Смотреть `db_updated` / `skipped_*` в логе.

## 7. Private / SEO rollout

```bash
php artisan tenant-storage:migrate-to-r2 --dry-run --only=seo \
  --from-private=local --to-private=r2-private
php artisan tenant-storage:migrate-to-r2 --only=seo \
  --from-private=local --to-private=r2-private \
  2>&1 | tee seo-wave-$(date +%F).log
```

Затем `.env` (согласованно):

```env
TENANT_STORAGE_PRIVATE_DISK=r2-private
SEO_FILES_DISK=r2-private
```

Снова `config:clear` → `config:cache` и рестарт workers при необходимости.

Проверки: robots/sitemap в кабинете и на сайте по контракту; нет случайной публикации URL приватного bucket.

## 8. После cutover (модель)

- Для приложения **primary** — объекты на R2 (public/private по назначению).
- Локальные копии под `storage/app/public` и т.п. — **legacy / резерв**, не источник истины; **не удалять** автоматически в этой фазе.

## 9. Не делать сейчас

- Не удалять local storage массово.
- Не переименовывать ключи (`site/*`, `media/*`).
- Не делать PR6 / namespace normalization.
- Не «оптимизировать заодно» вне этого чеклиста.

## Backlog позже

- Ручная чистка старых локальных файлов.
- PR6 — отдельно.
- Агрессивный prune — только по отдельному runbook.

---

На Windows вместо `tee` можно: `... 2>&1 | Tee-Object -FilePath branding-dry-run.log`.
