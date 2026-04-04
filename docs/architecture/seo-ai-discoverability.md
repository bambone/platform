# SEO / AI-discoverability (маркетинг платформы и публичные сайты тенантов)

Долгоживущий **операционный чеклист**: классический поиск, цитирование в AI-системах, Yandex/Alice, агентный поиск. Краткие правила для агента: [.cursor/rules/seo-ai-discoverability.mdc](../../.cursor/rules/seo-ai-discoverability.mdc).

## Scope

| Контур | Хост / код | Примечание |
|--------|------------|------------|
| Маркетинг платформы | `tenancy.central_domains`, `resources/views/platform/marketing/` | Например `rentbase.su` |
| Публичный сайт тенанта | Домен из `tenant_domains`, `resources/views/tenant/`, публичные маршруты в `routes/web.php` (группа `EnsureTenantContext`) | Каталог, CMS, бронирование |

## Canonical, robots, sitemap

- Один **основной** вариант домена (apex vs www) — политика 301 или единый **canonical**; в sitemap только **canonical** URL.
- Каждая значимая страница: свой **canonical** (сам на себя), осмысленный **200**; не вешать **noindex** на основной маркетинг без причины.
- **robots.txt**: отдача с **200**, строка `Sitemap:`; не редиректить robots на HTML.
- **Central marketing** (`tenancy.central_domains`, не тенант): `GET /robots.txt`, `/sitemap.xml`, `/llms.txt` — `PlatformMarketingRobotsController`, `PlatformMarketingSitemapController`, `PlatformLlmsTxtController` в `routes/web.php` (группа `$marketingHosts`).
- **Тенантские домены:** по-прежнему `RobotsController` / `SitemapController` в группе с `EnsureTenantContext`; экспериментальный `GET /llms.txt` — `TenantLlmsTxtController` (не заменяет sitemap).

## Матрица: тип страницы → JSON-LD (платформа)

| URL / тип | Рекомендуемые типы schema.org | Примечание |
|-----------|-------------------------------|------------|
| `/` (главная) | `Organization`, `WebSite`, `SoftwareApplication` | Данные = видимый контент; `WebSite` может включать `potentialAction` SearchAction только если реально есть поиск |
| `/faq` | `FAQPage` | Вопросы/ответы на странице = в разметке |
| `/pricing` | `SoftwareApplication` (цены как в тексте) | Поля офферов согласовать с отображаемыми тарифами |
| `/features`, `/contact` | По смыслу: часто достаточно `WebPage` + вложенный `Organization` / без дублирования всего стека главной | Не дублировать три одинаковых `SoftwareApplication` на каждой странице без необходимости |
| Вертикали (`/for-moto-rental`, …) | `Service` **или** `SoftwareApplication` | Один основной тип на страницу; не смешивать несовместимые свойства |
| Навигация с иерархией | `BreadcrumbList` | Там, где в UI есть цепочка разделов |

Тенантские страницы: по аналогии (организация/локальный бизнес, услуги, FAQ) — только то, что отражено в HTML.

## Политика crawler / AI

- Разделять **crawl**, **index** и **reuse / grounding**; `robots.txt` ≠ гарантированное «не в индексе» — для исключения из индекса нужен **noindex** (осознанно).
- Публичный маркетинг **не** закрывать без причины для: OAI-SearchBot, GPTBot, Googlebot; **Google-Extended** — отдельное продуктовое решение; Bingbot, PerplexityBot, ClaudeBot, CCBot, Yandex.
- Закрывать точечно: `/admin`, `/platform`, API, черновики, дубли с параметрами.

## Политика `llms.txt`

- **Экспериментальный** формат; **не** заменяет sitemap, нормальный HTML и JSON-LD.
- Содержание: кто такой RentBase (или бренд), 5–10 ключевых URL, по 1–2 строки summary на URL.
- **Тенант:** правка текста и JSON — Filament **Маркетинг → SEO-файлы**, секция «Настройки SEO» (`seo.llms_intro`, `seo.llms_entries`, при необходимости `seo.route_overrides`).
- Опционально: расширенный `llms-full.txt` / markdown для агентов.

## Webmaster tools (чеклист)

- [ ] Google Search Console — свойство, sitemap, проверка покрытия
- [ ] Bing Webmaster Tools — сайт, sitemap; при появлении — **AI citations / performance**
- [ ] Yandex Webmaster — сайт, sitemap, robots, регион при релевантности
- [ ] Yandex Metrica (и/или GA4) — по политике продукта
- [ ] Rich Results Test / валидаторы — после изменений JSON-LD

## Что нельзя ломать при рефакторингах

- Публичные маршруты маркетинга и тенанта без **редиректов 301** там, где меняется URL; обновлять sitemap и внутренние ссылки.
- Разделение хостов: маркетинг ≠ `PLATFORM_HOST` ≠ домен тенанта — не смешивать `robots`/sitemap между контурами.
- **Tenant scope:** публичные данные тенанта остаются с фильтрацией по `tenant_id` / резолвером темы; SEO-правки не должны открывать чужие данные.
- Не удалять server-rendered контент в пользу **единственного** клиентского JS для основного текста.
- Не сужать `robots.txt` до «закрыть всё подчистую» при security-hardening без явного списка исключений для AI/search ботов.

## What not to do (антипаттерны)

- Скрытый или микроскопический **SEO-текст** для ботов.
- Один и тот же **H1** на всех SEO-страницах платформы.
- **Фейковый FAQ** (вопросы без честных ответов или несуществующие услуги).
- **JSON-LD** с полями, которых **нет на странице** (цена, рейтинг, функции).
- Случайная **блокировка** crawler-ботов при «уборке» security / robots.
- Основной маркетинговый контент **только** через client-side hydration без эквивалента в HTML.

## Копирайт под AI-intent (answer-blocks)

- На каждой SEO-странице платформы — минимум **2–4 коротких блока**: «вопрос → прямой ответ» (заголовок + 2–4 предложения + при необходимости список).
- **FAQ** — формулировки вопросов **естественным языком** (как в поиске).
- **Entity-core** (кто такой RentBase, для кого, что делает) повторять **согласованно**, без расползания терминов и противоречий между URL.

## Ссылки на документацию вендоров

- [OpenAI Help](https://help.openai.com/)
- [Google Search Central](https://developers.google.com/search/docs)
- [Yandex Webmaster](https://yandex.ru/support/webmaster/)
- [Bing Webmaster](https://blogs.bing.com/webmaster/)
- [Perplexity](https://docs.perplexity.ai/)
- [Anthropic](https://support.anthropic.com/)
- [llms.txt](https://llmstxt.org/)
