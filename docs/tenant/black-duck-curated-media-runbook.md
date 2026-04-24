# Black Duck: curated media (операторский поток)

## Cutover / деплой (каталог в БД)

После миграции, создающей `tenant_media_assets`, до публичного трафика (или в одном релизе с миграцией) выполните:

1. **Импорт каталога в БД:** `php artisan tenant:black-duck:import-media-catalog-to-db {tenant}` (при повторных прогонах можно `--only-missing` — по вашему SOP).
2. **Синхронизация публичного контента:** `tenant:black-duck:refresh-content` (и снятие кэша, как принято в окружении).

**Важно:** при существующей таблице и **0 строках** для тенанта `BlackDuckMediaCatalog::loadOrEmpty()` **не** подставляет `media-catalog.json` с диска — пустой витринный снимок осознанный, не тихий fallback на JSON.

## Этап 1 (текущий): без UI для каталога

Операторский **SOP** без правки кода:

1. Заполнить **контентный реестр** (таблица со схемой в `database/data/black_duck_content_registry.example.csv`) — один смысловой ассет = одна строка; сверка с `source_ref` на диске.
2. Собрать `curated-manifest.json` (по реестру) и папку файлов; **`--dry-run`** импорта.
3. `tenant:black-duck:import-curated-proof` (при необходимости с `--force` — см. риск полной подмены каталога).
4. `tenant:black-duck:refresh-content {tenant} --force`.
5. Визуальный QA: главная, `/raboty` (сетка **не менее 12** пунктов в `works_portfolio` для приёмки — см. `BlackDuckServiceRegistry::MIN_WORKS_PORTFOLIO_ITEMS_ACCEPTANCE`), `/uslugi`, посадочные, отсутствие внешних URL в proof.

## Hub `/uslugi` (MVP, вариант A)

- **Группы** (`groups` в `data_json` секции `service_hub`) формируются **только** в `BlackDuckContentRefresher::updateServiceHub()` из `BlackDuckServiceRegistry` при `tenant:black-duck:refresh-content`. Оператор **не** перенастраивает бизнес-группы через page builder: поле `groups` в UI не отражает то, что уйдёт на публикуемую страницу после refresh.
- Ручное редактирование **плоского** `data_json.items` в Filament (если доступно) не подменяет сгруппированный рендер у Black Duck; для смены состава/групп правьте реестр услуг и снова запускайте refresh.

**Этап 2 (отдельная задача):** при необходимости — Filament-редактор `media-catalog.json` с валидацией v3. Не блокирует приёмку контента по SOP выше.

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
