# Black Duck (tenant 4): раскладка медиа по ТЗ

Дата: 2026-04-23. Исходники: `C:\OSPanel\home\rentbase-media\tenants\4\public\site\brand\proof\XXXL*.webp` (48 файлов).

## Источники правды

| Назначение | Путь на диске `public` |
|------------|-------------------------|
| Обложки услуг (хаб, превью главной) | `site/brand/services/{slug}.webp` |
| Hero главной | `site/brand/hero-1916.webp` (из `XXXL (8).webp`) |
| Портфолио `/raboty`, фильтры | `media-catalog.json` → роль `works_gallery`, файлы `site/brand/proof/wg-01.webp` … `wg-25.webp` |
| Галерея `service_proof` на посадочных | `media-catalog.json` → роль `service_gallery`, путь `site/brand/services/{slug}.webp` для 6 slug |
| Полный архив кадров | `site/brand/proof/XXXL*.webp` (копия всего сета) |

**Важно:** `home_service_card` в каталоге не используется — обложки идут из `services/{slug}.webp` (см. `BlackDuckMediaCatalog::homeServiceHubImage` → fallback `BlackDuckServiceImages::firstExistingPublicPath`).

## Обложки `services/*.webp` (по смыслу ТЗ)

| slug | Исходный файл |
|------|----------------|
| polirovka-kuzova | XXXL (21).webp |
| keramika | XXXL (25).webp |
| ppf | XXXL (11).webp |
| tonirovka | XXXL (32).webp |
| himchistka-salona | XXXL (24).webp |
| shumka | XXXL (19).webp |
| detejling-mojka | XXXL (12).webp |
| podkapotnaya-himchistka | XXXL (2).webp |
| kozha-keramika | XXXL (45).webp |
| pdr | XXXL (22).webp |
| himchistka-kuzova | XXXL (7).webp |
| himchistka-diskov | XXXL (15).webp |
| antidozhd | XXXL (16).webp |
| bronirovanie-salona | XXXL (38).webp |
| remont-skolov | XXXL (14).webp |
| restavratsiya-kozhi | XXXL (10).webp |
| setki-radiatora | XXXL (1).webp |
| predprodazhnaya | XXXL (43).webp |
| vinil | XXXL (26).webp |

Не использованы как primary (по ТЗ «слабые» для обложек): отдельно не назначались `(5)`, `(17)`, `(18)`, `(20)`, `(28)`, `(29)`, `(30)`, `(40)`.

## Портфолио `wg-01` … `wg-25`

Сбалансированный набор (result / process / detail / studio) — соответствие исходников см. скрипт `scripts/black-duck/publish-media-tenant4.ps1` (массив `$workGrid`). Метаданные (slug, заголовки, теги) — `scripts/black-duck/build-media-catalog-tenant4.php`.

## Скрипты

1. `scripts/black-duck/publish-media-tenant4.ps1` — синхронизация proof, копии в `services/`, `hero-1916.webp`, `wg-NN.webp`.
2. `scripts/black-duck/build-media-catalog-tenant4.php` — пересборка `media-catalog.json` (31 asset: 25 works_gallery + 6 service_gallery), запись в `storage/app/public/tenants/4/public/site/brand/` и зеркало `rentbase-media`.
3. `scripts/black-duck/verify-tenant4-catalog.php` — быстрая проверка загрузки каталога и ключевых путей.

После изменений: `php artisan tenant:black-duck:refresh-content blackduck --force`.

## Продакшен: локально фото есть, на проде нет (или «все картинки как один плейсхолдер»)

Публичные URL для `/raboty` собирает `ExpertBrandMediaUrl` + `TenantPublicAssetResolver` из **object key** вроде `site/brand/proof/wg-*.webp`. Подписи к карточкам идут из `data_json` / `media-catalog.json`; **сами файлы** должны быть в публичном storage тенанта **на проде** (R2, CDN или `MEDIA_LOCAL_ROOT` + nginx `/media/…`), а не только на машине разработчика.

1. **Диагностика на сервере:** `php artisan tenant:black-duck:verify-public-media blackduck` — печатает `version` и **число `assets` в фактическом** `site/brand/media-catalog.json` на storage, затем проверяет `exists` по путям. Сверьте с репозиторием: в `database/data/black_duck_tenant4_media_catalog.json` (актуальная срезка) сейчас **18** записей `assets` и **18** уникальных путей в проверке `all`. Если на проде, например, **9** путей и 9 ok — файлы на месте, но **каталог на R2/зерке устарел или урезан** относительно репо: перезалить тот же `media-catalog.json` + недостающие `site/brand/proof/*`, затем `refresh-content`. С `--strict` ненулевой код, если чего-то нет. Узко по портфолио: `--role=works_gallery`.
2. **Исправление:** выложить тот же набор, что даёт `scripts/black-duck/publish-media-tenant4.ps1` (в шапке скрипта: зеркало → **загрузка в R2**), при необходимости `tenant-media:backfill-from-r2` — см. [tenant-media-local-mirror.md](../operations/tenant-media-local-mirror.md). Затем на проде: `php artisan tenant:black-duck:refresh-content blackduck --force`.
3. **Инвариант:** ключи `tenants/{id}/public/site/brand/...` должны существовать в хранилище, с которым сконфигурировано приложение. Каталог **только** в `C:\OSPanel\home\rentbase-media\...` без синхронизации в бакет/зерко **не сделает** картинки на боевом домене.
4. **Hero:** приоритет — `hero-1916.webp` (и WebP слайса), **не** `hero-1916.jpg`. Старый JPEG на проде мог мешать; при желании удалите дубликат в storage.

## Проверка содержимого файла

Если кадр по факту не совпадает с ожиданием по номеру — править привязку в `publish-media-tenant4.ps1` и перезапускать скрипты выше.
