# Moto Levins: продакшен — данные и статика

Цель: на проде ([motolevins.rentbase.su](https://motolevins.rentbase.su/)) те же **относительные пути**, что и локально: файлы в `public/`, без привязки к абсолютным путям на сервере.

## Куда класть бинарники (как в репозитории)

Скопируйте каталог с локальной машины в корень приложения на прод:

| Локально (репозиторий) | URL в браузере |
|------------------------|----------------|
| `public/images/motolevins/bikes/*.jpg` | `/images/motolevins/bikes/...` |
| `public/images/motolevins/marketing/*` | `/images/motolevins/marketing/...` |
| `public/images/motolevins/avatars/*.png` | `/images/motolevins/avatars/...` |
| `public/images/motolevins/icons/*.png` | `/images/motolevins/icons/...` |
| `public/images/motolevins/videos/Moto_levins_1.mp4` (hero, опционально) | `/images/motolevins/videos/Moto_levins_1.mp4` |

Префикс и имя hero-ролика задаются в `config/tenant_landing.php` (`motolevins_public_prefix`, `motolevins_hero_video`). Видео относится к материалам тенанта, не к общей «платформенной» папке `public/videos/`.

## Переменные окружения

- `APP_URL` — URL приложения (для генерации ссылок в консоли и части фолбэков; **схема** `http`/`https` для сидера ниже берётся отсюда).
- `TENANCY_ROOT_DOMAIN` — корень зоны поддоменов; сидер `MotoLevinsTenantSeeder` пишет в `tenant_settings.general.domain` канонический URL вида `{scheme}://motolevins.{TENANCY_ROOT_DOMAIN}` (тот же хост, что создаёт `TenantDomainService::createDefaultSubdomain`). Отдельной переменной на каждого тенанта не требуется.
- `TENANT_DEFAULT_HOST` — опционально: дополнительный хост в `tenant_domains` для dev (например `localhost`). **Не** задавайте им маркетинговый apex (`rentbase.local` / `rentbase.su` из `TENANCY_CENTRAL_DOMAINS`). Канонический URL тенанта: `https://{slug}.{TENANCY_ROOT_DOMAIN}` (локально `http://motolevins.rentbase.local`).

После правок `tenant_settings` сбросьте кэш приложения (`php artisan optimize:clear`), т.к. значения кешируются.

## Обновление БД без новых миграций

Файлы в `database/scripts/motolevins/` — это **ручные SQL-скрипты**, не классы миграций Laravel (`php artisan migrate` их не выполняет).

### Пустой каталог на проде (как в актуальном дампе)

Типичная картина: есть `tenants`, `tenant_domains`, пользователи, роли, **`01_tenant_settings.sql` уже выполнен**, а таблицы `bikes`, `motorcycles`, `pages`, `page_sections`, `reviews`, `faqs` **пустые**.

- **`02_fix_public_image_paths.sql` не нужен** — обновления затронут 0 строк; можно не запускать. Пути к картинкам появятся при вставке данных сидерами или скриптом `03`.
- Дальше либо выполните **`03_landing_catalog_and_cms.sql`** (полный лендинг в БД одним файлом), либо **сидеры** (вариант B ниже). `MotoLevinsTenantSeeder` при желании можно пропустить, если тенант и `tenant_settings` уже на месте.

### Вариант A — только SQL

1. Убедитесь, что в `tenants` есть строка со `slug = 'motolevins'`.
2. В `01_tenant_settings.sql` при необходимости замените значение `general.domain` на актуальный URL.
3. `01_tenant_settings.sql` — контакты, название, домен, цвет (`ON DUPLICATE KEY UPDATE`).
4. **`03_landing_catalog_and_cms.sql`** — категории, 8 `bikes`, 8 `motorcycles` (каталог на главной), страница `home`, все секции hero/контент (JSON), FAQ, отзывы. Скрипт сначала **удаляет** у этого тенанта старые строки в этих таблицах (удобно перезапускать); порядок выполнения на чистом проде: `01` → `03`. Схема соответствует дампу `platform` (MySQL 8).
5. **`02_fix_public_image_paths.sql`** — только если уже есть данные со старыми путями `bikes/...`. На пустой БД пропустите.

### Вариант B — сидеры Laravel (рекомендуется для страниц и каталога)

На сервере, из корня проекта, с тем же `.env`, что и у веб-приложения.

**Минимально для прода, где тенант и настройки уже есть** (после `01`):

```bash
php artisan db:seed --class=Database\\Seeders\\BikeSeeder
php artisan db:seed --class=Database\\Seeders\\MigrationBikesToMotorcyclesSeeder
php artisan db:seed --class=Database\\Seeders\\BackfillMotorcyclesDataSeeder
php artisan db:seed --class=Database\\Seeders\\PagesAndSectionsSeeder
php artisan db:seed --class=Database\\Seeders\\FaqSeeder
php artisan db:seed --class=Database\\Seeders\\ReviewSeeder
```

**Полный набор с пересинхронизацией настроек тенанта** (идемпотентно, можно и на пустой БД):

```bash
php artisan db:seed --class=Database\\Seeders\\MotoLevinsTenantSeeder
php artisan db:seed --class=Database\\Seeders\\BikeSeeder
php artisan db:seed --class=Database\\Seeders\\MigrationBikesToMotorcyclesSeeder
php artisan db:seed --class=Database\\Seeders\\BackfillMotorcyclesDataSeeder
php artisan db:seed --class=Database\\Seeders\\PagesAndSectionsSeeder
php artisan db:seed --class=Database\\Seeders\\FaqSeeder
php artisan db:seed --class=Database\\Seeders\\ReviewSeeder
```

Сидеры **идемпотентны** там, где используется `updateOrCreate` / `firstOrCreate`. Чистый стенд с нуля: допустим полный `php artisan db:seed` (порядок в `DatabaseSeeder`).

## Проверка

- Главная открывается, hero и карточки маршрутов подтягивают картинки из `/images/motolevins/...`.
- Кнопка «Смотреть видео» на hero открывает ролик с URL вида `/images/motolevins/videos/Moto_levins_1.mp4` (файл лежит рядом с остальными материалами тенанта).
- В админке тенанта контакты и название совпадают с лендингом.
- Каталог мотоциклов показывает обложки из **Spatie Media** (коллекция `cover` у модели `Motorcycle`). Старое поле `cover_image` удалено; при деплое миграция `2026_03_30_120000_migrate_motorcycle_cover_image_to_media_and_drop_column` переносит пути из `cover_image` в медиатеку, если колонка ещё есть.

Если в `page_sections` для `hero` уже сохранён старый путь `videos/Moto_levins_1.mp4`, обновите поле в админке CMS или перезапустите `PagesAndSectionsSeeder` — иначе браузер будет запрашивать несуществующий `/videos/...`.
