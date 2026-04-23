# Black Duck: curated media (операторский поток)

## Идея

- **Яндекс.Карты, галереи и прочие HTTP-ссылки** — только вход для отбора и поле `source_ref` в манифесте. В вёрстке **нельзя** использовать внешние URL как финальные `src` для proof.
- Источник правды для главной, `/raboty` и `service_proof` — **локальные файлы** в хранилище тенанта и **`site/brand/media-catalog.json`** (`BlackDuckMediaCatalog`).

## Шаги

1. Получить от клиента папку экспорта (оригиналы фото/видео + список ролей).
2. Собрать **`curated-manifest.json`** в корне папки (ориентир **`version`: 3** и поля v3 — см. `database/data/black_duck_media_catalog.example.json`; v2-манифесты по-прежнему нормализуются без правок).
3. Выполнить импорт:

   ```bash
   php artisan tenant:black-duck:import-curated-proof blackduck --source=/path/to/export
   ```

   - **`--dry-run`** — проверка манифеста без записи в хранилище.
   - **`--force`** — перезапись файла, если ключ `site/brand/proof/...` уже есть; каталог после импорта **полностью заменяется** списком `assets` из этого манифеста.
   - Без **`--force`** (файлы): при коллизии пути импорт завершится ошибкой. Без **`--force`** (каталог): к существующему каталогу добавляются новые строки; записи с тем же «слотом» (role + service_slug + page_slug + before_after_group + sort_order), что у импортируемых, удаляются и заменяются новыми.

4. Синхронизировать секции:

   ```bash
   php artisan tenant:black-duck:refresh-content blackduck --force
   ```

5. Проверить визуально по чек-листу ниже.
6. Опубликовать.

## Распределение медиа по страницам

- **Главная** — минимальный proof (одна пара до/после или короткий превью-ряд без перегруза).
- **`/raboty`** — основная витрина: видео в `works_hero` при роли `works_featured_video`, секция **Портфолио** (`works_portfolio`: сетка, фильтры по `service_slug`/`tags`, лайтбокс), до/после, при необходимости блок «Проекты».
- **Карточки `/uslugi`** и превью на главной — обложка из `home_service_card` по услуге, иначе featured `service_gallery`, иначе legacy-изображения услуги.
- **Посадочные** (`SERVICE_PROOF_LANDING_SLUGS`) — выборочный компактный `service_proof`; блок скрыт, если в каталоге нет подходящих кадров. Видео услуги (`service_featured_video`) в hero с `video_deferred`: постер обязателен, без автоплея со звуком.

## Манифест v3 (обратно совместим с v2)

Дополнительные необязательные поля строки ассета нормализуются в `BlackDuckMediaCatalog::normalizeAssetRow()`:

`title`, `summary`, `service_label`, `tags`, `aspect_hint`, `display_variant`, `badge`, `cta_label`, `show_on_home`, `show_on_works`, `show_on_service`, `works_group`, `derivatives` (для `srcset` в вёрстке).

Старые манифесты без этих ключей продолжают работать.

Чипы фильтра на `/raboty`: сначала **«Все»**, затем услуги в порядке **`serviceMatrixQ1()`** (затем slug’и вне матрицы по алфавиту), затем теги по **`SORT_NATURAL`**.

## QA после refresh-content --force

- Главная: не более одного «режима» proof (до/после **или** карточки с фото); пустые блоки скрыты.
- `/raboty`: при наличии `works_gallery` / `works_case_card` — сетка портфолио и при необходимости чипы фильтра; лайтбокс открывается с клавиатуры; финальный CTA на заявку.
- Услуга: `service_proof` только при непустой галерее; обложка в хабе услуг совпадает с ожидаемой цепочкой каталога.
- Нет пустых контейнеров `<video>` без валидной пары `video`+`poster`.

## Роли каталога

См. `App\Tenant\BlackDuck\BlackDuckMediaRole` и PHPDoc у `BlackDuckMediaCatalog`.

## Запасной импорт «утиных» фото в hub

Команда `tenant:black-duck:import-duck-media` не трогает proof, если в каталоге уже есть валидные локальные curated-записи (`hasCuratedManifest`).
