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

- `APP_URL` — URL приложения (для генерации ссылок в консоли и части фолбэков).
- `TENANT_MOTOLEVINS_PUBLIC_URL` — публичный URL клиентского сайта (например `https://motolevins.rentbase.su`), подставляется сидером `MotoLevinsTenantSeeder` в `tenant_settings.general.domain`, если вы заполняете БД через Laravel, а не только SQL.
- `TENANT_DEFAULT_HOST` — хост, на котором открывается тенант (локально часто `motolevins.local`).

После правок `tenant_settings` сбросьте кэш приложения (`php artisan optimize:clear`), т.к. значения кешируются.

## Обновление БД без новых миграций

Файлы в `database/scripts/motolevins/` — это **ручные SQL-скрипты**, не классы миграций Laravel (`php artisan migrate` их не выполняет).

### Пустой каталог на проде (как в актуальном дампе)

Типичная картина: есть `tenants`, `tenant_domains`, пользователи, роли, **`01_tenant_settings.sql` уже выполнен**, а таблицы `bikes`, `motorcycles`, `pages`, `page_sections`, `reviews`, `faqs` **пустые**.

- **`02_fix_public_image_paths.sql` не нужен** — обновления затронут 0 строк; можно не запускать. Пути к картинкам появятся при вставке данных сидерами.
- Дальше на сервере выполните **сидеры** (вариант B ниже), начиная с каталога и контента. `MotoLevinsTenantSeeder` при желании можно пропустить, если тенант и `tenant_settings` уже на месте.

### Вариант A — только SQL

1. Убедитесь, что в `tenants` есть строка со `slug = 'motolevins'`.
2. В `01_tenant_settings.sql` при необходимости замените значение `general.domain` на актуальный URL.
3. `01_tenant_settings.sql` — контакты, название, домен, цвет (`ON DUPLICATE KEY UPDATE`).
4. **`02_fix_public_image_paths.sql`** — имеет смысл **только после** импорта/миграции старых данных, где в БД уже лежат пути вида `bikes/...` или пустые аватары у нужных отзывов. На пустой БД пропустите.

Контент главной (`pages` / `page_sections`) в отдельные SQL мы не выносили: удобнее заполнить сидерами.

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
- Каталог мотоциклов показывает обложки; в БД поля `cover_image` / `image` начинаются с `motolevins/bikes/` (или полный путь `images/...` для особых случаев).

Если в `page_sections` для `hero` уже сохранён старый путь `videos/Moto_levins_1.mp4`, обновите поле в админке CMS или перезапустите `PagesAndSectionsSeeder` — иначе браузер будет запрашивать несуществующий `/videos/...`.
