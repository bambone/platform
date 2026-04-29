# Magas tenant (RentBase) — v1 операционная сводка

Реализует позиционирование из плана onboarding (конверсионный экспертный сайт B2B PR / Web3 на теме `expert_pr`). Этот файл — **операционная фиксация** решений v1; файл плана в `.cursor/plans/` не дублируем.

## 1. Canonical brand spelling (EN)

- **Единое написание:** **Sergei Magas** — в `meta`, H1 где от бренда, JSON-LD `Person.name`, подписи контактов и bootstrap (`MagasExpertBootstrap::BRAND`).
- Вариант «Sergey» в старых источниках считается **несовпадающим** с v1; любые правки — через Filament после запуска.

## 2. v1 SEO-контур: домен и локаль

| Параметр | Значение |
|----------|-----------|
| Apex (канон) | `https://sergeymagas.com` (без `www` в URL) |
| Slug тенанта | `sergey-magas` (`MagasExpertBootstrap::SLUG`) |
| Локаль тенанта | `en` (`locale` в `tenants`) |
| Публичный базовый URL для sitemap/SEO | `TenantCanonicalPublicBaseUrl` читает **`general.domain`** |

Команда без флага держит черновой режим: см. «Prelaunch».

## 3. Политика `sergeymagas.ru` до фазы платформы

- **RentBase v1** обслуживает **только** домен в `tenant_domains` + `general.domain` (**`.com`**).
- Если **живой** `.ru` остаётся на стороннем хостинге как отдельное свойство — **не подключать** его к этому тенанту как вторичный SEO-хост без фазы multilingual; без согласованного сценария **не ставить автоматический 301 .ru→.com**.
- После платформенного эпика: host→locale, `hreflang`, dual sitemap (см. раздел Phase 2).

## 4. Nested `/services/{segment}` и CMS

Поддержано штатно:

- Маршрут `routes/web.php`: `GET /services/{nested_slug}` → `PageController::showServiceNested` формирует slug страницы `services/{nested_slug}` ([`PageController`](../../../app/Http/Controllers/PageController.php)).
- Обычная CMS-страница: `GET /{slug}` с односегментными slug из `[a-z0-9\-]+`; общий контур `/services/{segment}` нужен именно для **вложенного** slug со слэшем.
- Резолв вьюхи: [`CustomPageResolver`](../../../app/Services/CustomPageResolver.php) нормализует slash в имени страницы.

## 5. Canonical host (`www`, схема)

- SEO-URL всегда с **канонического base**, не с хоста админки: [`TenantCanonicalPublicBaseUrl`](../../../app/Services/Seo/TenantCanonicalPublicBaseUrl.php).
- **HTTP→HTTPS** и TLS — на edge (nginx/Cloudflare); приложение не дублирует TLS-терминацию.
- **www→apex:** в production подключён middleware [`RedirectWwwTenantToCanonicalPublicUrl`](../../../app/Http/Middleware/RedirectWwwTenantToCanonicalPublicUrl.php) (включается `config('tenancy.redirect_www_to_canonical_apex', true)` / `TENANCY_REDIRECT_WWW_TO_CANONICAL`).

## 6. Контакт: URL

- В плане упомянут `/contact`; в приложении канонический путь **`/contacts`**, добавлен **`301 /contact → /contacts`**, **`301 /kontakty → /contacts`** (`routes/web.php`).
- Brief form и блоки секций должны использовать **`/contacts#…`** или якорь формы там же.

## 7. Предварительная индексация / prelaunch

- Bootstrap Artisan: **`php artisan tenant:magas:bootstrap`** без `--publish` создаёт страницы в **`draft`**, домашняя и внутренние **SeoMeta** с **`is_indexable = false`** (поиск не зовём до ревью).
- При готовности к индексу: **`php artisan tenant:magas:bootstrap --publish`** (и финальный контент в Filament).
- Sitemap включает только **published** страницы и только если Seo **`is_indexable`** ([`SitemapUrlProvider`](../../../app/Services/Seo/SitemapUrlProvider.php)). Статические пути (`/faq` и др.) из `config/seo_sitemap.php` — при необходимости убрать лишнее для PR-сайта в админке конфига окружения.

## 8. IA → типы секций (главная)

| Блок (логика) | `section_key` / тип | Примечание |
|---------------|---------------------|------------|
| Hero | `expert_hero` / `expert_hero` | Регистрация в PageSectionTypeRegistry |
| Проблемы | `problem_cards` / `problem_cards` | |
| Обзор услуг | `services_teaser` / `cards_teaser` | Ссылки на `/services/...` |
| Процесс | `process_steps` / `process_steps` | |
| Био | `founder_expert_bio` / `founder_expert_bio` | |
| FAQ preview | `faq` / `faq` | Данные из таблицы `faqs` |
| Форма | `expert_lead_form` / `expert_lead_form` | CRM `expert_service_inquiry` |

Новые типы — только через реестр, не разовые Blade без типа.

## 9. Bootstrap безопасность

- Только команда **`tenant:magas:bootstrap`**; **не** добавляется в [`DatabaseSeeder`](../../../database/seeders/DatabaseSeeder.php).
- PK тенанта: опция **`--canonical-id=`** при свободном слоте; см. [`MagasExpertBootstrap`](../../../database/seeders/Tenant/MagasExpertBootstrap.php).

## 10. Форма, CRM, спам

- API: `POST /api/tenant/expert-inquiry` — throttling, honeypot `website`, expert_pr: **телефон или email**, payload в CRM + `contact_email` в `payload_json` при наличии.
- См. [`ExpertInquiryController`](../../../app/Http/Controllers/ExpertInquiryController.php), [`StoreExpertInquiryRequest`](../../../app/Http/Requests/StoreExpertInquiryRequest.php).

## 11. Структурированные данные

- Главная: **Person** в JSON-LD (без лишней Organization).
- Страницы услуг: **Service** в seed SEO при `--publish`/актуальном SeoMeta.
- FAQ: отдельный маршрут `/faq`; FAQPage schema — только если совпадает с видимым контентом и поддерживается генератором.

## 12. Медиа

- Публичные URL только через **tenant public resolver** (`TenantPublicAssetResolver` / конфиг CDN); в HTML конструировать URL через хелперы темы — не вставлять «сырой» endpoint R2.

## 13. Performance (v1 baseline)

- Ниже первого экрана — ленивое монтирование секций в теме (`expert_home` lazy hosts); Hero — без искусственно тяжёлого видео в v1.
- Ширину/высоту медиа задавать по месту в секциях чтобы снизить CLS.

## 14. Phase 2 (мультиязычность / .ru как локаль платформы)

- Отдельный объём: `sergeymagas.ru`, host→locale, взаимные **абсолютные** alternate/hreflang, dual sitemap, `TenantPublicHtmlLang`, правки Filament по локалям.

---

**См. также:** [`docs/architecture/seo-ai-discoverability.md`](../../architecture/seo-ai-discoverability.md) (общие инварианты платформы).
