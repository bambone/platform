# Page builder smoke tests

Отдельный regression/smoke-контур для публичного рендера страниц конструктора (content partials, `SectionViewResolver`, шаблон главной) и лёгкая проверка HTML билдера в админке (Livewire).

## Запуск

Короткий alias (рекомендуется локально):

```bash
composer test:page-builder-smoke
```

Полный набор в каталоге `tests/Feature/PageBuilder/Smoke`:

```bash
php artisan test --testsuite=PageBuilderSmoke
```

По PHPUnit-группе (удобно, если позже появятся ещё классы с той же меткой):

```bash
php artisan test --group=page-builder-smoke
```

Обычно для изменений конструктора достаточно **`--testsuite=PageBuilderSmoke`** или **`composer test:page-builder-smoke`**; **`--group`** — если классы с `@group page-builder-smoke` окажутся вне папки Smoke.

Обычный прогон `php artisan test` / `composer test` **не** включает эти тесты: каталог `Smoke` исключён из suite `Feature` в `phpunit.xml`.

## CI

GitHub Actions: в workflow **CI** (`.github/workflows/ci.yml`) джоба **page-builder-smoke** ставится в очередь, если в коммите затронуты пути page builder / секций / smoke-тестов (тот же список, что раньше в отдельном workflow). Ручной запуск: **Actions → CI → Run workflow** (на `master` после тестов пойдёт и **deploy**). На шаге smoke задано `APP_ENV=testing` (дублирует `phpunit.xml`, страховка для окружения).

## Когда обязательно гонять

После изменений в:

- `app/PageBuilder/**`
- `app/Services/PageBuilder/**` (в т.ч. `SectionViewResolver`)
- `resources/views/tenant/themes/*/sections/**`
- `resources/views/tenant/pages/page.blade.php`
- `app/Livewire/Tenant/PageSectionsBuilder.php`
- `app/Http/Controllers/HomeController.php` и `resources/views/tenant/pages/home.blade.php`
- реестр / категории / page-context каталога секций

## Что проверяется

**Content (`PageBuilderContentPublicRenderSmokeTest`):**

- Все 8 контентных типов на одной странице с фиксированным `sort_order` 10…80; порядок маркеров в HTML; `main` раньше первого `data-page-section-type` у extra-блоков.
- Каждый тип отдельно как единственный extra-блок (ловит регрессии «в толпе работает, в одиночку нет»).
- Edge-фикстуры с пустыми optional-полями.
- Секции `is_visible = false` и `status = draft` не попадают в публичный HTML.
- У каждого типа: корневой wrapper с `data-page-section-type="…"`, уникальный маркер внутри wrapper, непустой текст контейнера. Отсутствие partial / неверный `SectionViewResolver` → нет wrapper или маркера.

**Home (`PageBuilderHomePublicRenderSmokeTest`):**

- Минимальное окружение (tenant, `home`, ключи секций под шаблон), пустой каталог мотоциклов допустим. Проверка стабильности рендера шаблона, не полное визуальное покрытие landing.

**Admin (`PageBuilderAdminRenderSmokeTest`):**

- HTML регрессия Livewire-билдера (не E2E): каталог блоков, плашка основного контента, строка секции в списке, в каталоге non-home нет `startAdd('hero')` и лейбла **Hero** (нет bleed landing-каталога на обычную страницу).

## Фикстуры и пресеты

`tests/Support/PageBuilderSmokeFixtures.php` — маркеры, строки секций, `homeSectionRows()`. Пресеты для будущих сценариев:

- `richContentFixture()` — как полная rich-страница;
- `legalPageFixture()` — main + один `notice_box`;
- `faqHeavyFixture()` — main + `content_faq` с несколькими пунктами;
- `contactsMinimalFixture()` — main + `contacts_info` только телефон.

## Файлы

- `tests/Support/PageBuilderSmokeFixtures.php` — маркеры, секции, пресеты.
- `tests/Support/AssertsHtmlMarkerOrder.php` — порядок подстрок и wrapper+маркер (DOM).
- `tests/Support/InteractsWithTenantSmokeHttp.php` — `getTenantHtmlResponse()`.
