# Промт для Cursor: разделить текущую админку на Platform Console и Tenant Admin

Нужно выполнить **архитектурное разделение текущей системы** на 3 независимые зоны:

1. **Platform Website** — публичный продающий сайт самой платформы
2. **Platform Console** — внутренняя платформенная админка для владельца платформы и platform staff
3. **Tenant Admin** — клиентская админка конкретного tenant
4. **Tenant Public Site** — публичный сайт клиента на его домене/поддомене

Сейчас система уже работает на Laravel и уже содержит:

* multi-tenancy foundation;
* tenant resolution;
* tenant-scoped модели;
* CMS;
* каталог;
* leads;
* bookings;
* rental units;
* onboarding wizard;
* branding;
* SEO;
* роли;
* Filament admin.

Но сейчас в админке **перемешаны platform-level и tenant-level разделы**, и это **нужно исправить архитектурно**, а не просто скрытием меню.

---

# 1. Главная цель

Сделать так, чтобы система была жёстко разделена на отдельные зоны доступа:

## Platform Website

Публичный сайт платформы, где продаётся сам продукт.

## Platform Console

Закрытая панель для platform staff:

* управление клиентами;
* управление планами;
* шаблоны;
* домены;
* platform settings;
* support / impersonation;
* global dashboard.

## Tenant Admin

Закрытая панель клиента:

* сайт клиента;
* каталог;
* бронирования;
* leads;
* customers;
* rental units;
* branding;
* SEO;
* settings;
* команда клиента.

## Tenant Public Site

Публичный сайт клиента:

* лендинг;
* каталог;
* карточки транспорта;
* booking flow;
* формы.

---

# 2. Главная проблема, которую нужно решить

Сейчас platform-level функциональность и tenant-level функциональность смешаны в одной админке.

Это **неприемлемо**, потому что:

* клиент не должен видеть platform-level сущности;
* клиент не должен иметь даже теоретическую возможность попасть в разделы platform admin;
* platform staff не должен работать в tenant admin “по умолчанию”;
* создание клиентов, планы, шаблоны и platform dashboard не должны жить в tenant admin панели;
* нельзя решать это только через `visible()`, `shouldRegisterNavigation()` и скрытие пунктов меню.

## Требование

Разделение должно быть выполнено на **трёх уровнях**:

1. **Separate panels**
2. **Access middleware / host rules**
3. **Policies / permissions / tenant checks**

---

# 3. Итоговая целевая структура

## 3.1. Platform Website

Публичный сайт платформы.

Назначение:

* продавать платформу;
* показывать возможности;
* показывать тарифы;
* собирать лиды;
* показывать кейсы;
* FAQ;
* форма заявки / демо.

### Пример маршрутов

* `/`
* `/features`
* `/pricing`
* `/use-cases`
* `/faq`
* `/contact`
* `/demo-request`

### Важно

Это **не Filament panel**.
Это обычный Laravel public layer.

---

## 3.2. Platform Console

Закрытая платформенная панель для super admin / platform staff.

### Название в продукте

Использовать название:

## **Platform Console**

Не называть в UI “Супер админка”.

### Пример доступа

* `platform.yourdomain.com`
  или
* локально `platform.motolevins.local`

### Platform Console должна содержать:

* Dashboard
* Clients / Tenants
* Tenant Domains
* Plans
* Template Presets
* Platform Users
* Platform Settings
* Feature Flags
* Billing
* Support / Impersonation
* System Audit / Health
* Integrations Health

---

## 3.3. Tenant Admin

Закрытая панель клиента.

### Пример доступа

* `tenant-subdomain.yourdomain.com/admin`
* `clientdomain.com/admin`

### Tenant Admin должна содержать:

* Dashboard
* Website / CMS
* Catalog
* Rental Units
* Leads
* Customers
* Bookings
* Pricing
* SEO
* Branding
* Reviews
* FAQ
* Team
* Settings

### Важно

Tenant Admin не должна содержать:

* Clients / Tenants
* Plans
* Platform Settings
* Template Presets глобального уровня
* Platform billing
* Support tools
* Global dashboard

---

## 3.4. Tenant Public Site

Публичный сайт клиента.

### Пример доступа

* `motolevins.yourdomain.com`
* `clientdomain.com`

---

# 4. Архитектурные правила

## 4.1. Нельзя оставлять одну общую панель и просто скрывать разделы

Это запрещено.

Нужны:

* отдельный `PlatformPanelProvider`
* отдельный `TenantPanelProvider` (или существующий `AdminPanelProvider`, но уже строго tenant-only)

---

## 4.2. Platform Console и Tenant Admin должны быть физически разделены

Должны быть разные:

* panel providers;
* menu;
* resources;
* access rules;
* dashboard widgets;
* middleware rules.

---

## 4.3. Platform-level ресурсы не должны регистрироваться в tenant панели

И наоборот:

* tenant ресурсы не должны автоматически регистрироваться в platform panel без отдельной причины.

---

# 5. Что нужно сделать

---

# 5.1. Создать отдельную панель Platform Console

Создать новый Filament panel provider:

* `app/Providers/Filament/PlatformPanelProvider.php`

## Platform panel должна:

* работать только для platform users;
* открываться только на platform host;
* не требовать tenant context;
* не использовать tenant-scoped resources как основной слой управления.

## Конфигурация

Нужно продумать и реализовать:

* path/domain panel;
* auth middleware;
* navigation;
* dashboard;
* branding;
* panel-specific resources;
* panel-specific pages.

### Рекомендуемый вариант

Использовать отдельный host:

* `platform.yourdomain.com`
* локально `platform.motolevins.local`

---

# 5.2. Очистить Tenant Admin от platform-level разделов

Из текущей клиентской панели убрать всё, что относится к платформе:

Убрать из tenant admin:

* создание клиентов;
* tenants list;
* plans;
* template presets глобального уровня;
* platform settings;
* platform users;
* support pages;
* глобальный billing;
* global health pages.

Оставить только tenant-scoped ресурсы и разделы.

---

# 5.3. Ввести чёткое разделение доступа

## Platform access rules

В Platform Console могут входить только пользователи с platform role:

* `platform_owner`
* `platform_admin`
* `support_manager` (если нужно)

### Проверки

* tenant user без platform role не должен иметь доступ в Platform Console;
* даже если знает URL — должен получить deny.

---

## Tenant access rules

В Tenant Admin могут входить только пользователи, у которых есть membership в текущем tenant через `tenant_user`.

### Проверки

* platform user без membership в tenant не должен автоматически видеть tenant admin;
* доступ возможен только через отдельную support/impersonation политику в будущем.

---

## Unknown / invalid access

Если пользователь:

* открыл не ту панель;
* не имеет прав;
* не состоит в tenant;
* пытается зайти на чужой host;

нужно отдавать:

* 403 / access denied,
* либо redirect на правильную зону,
* но без утечки информации о существовании tenants.

---

# 5.4. Реализовать host-aware panel access

Нужно жёстко закрепить правила доступа по host.

## Правила

### Platform host

* `platform.yourdomain.com`
* открывает только Platform Console
* tenant context не резолвится как клиентский tenant

### Tenant admin host

* `tenant-subdomain.yourdomain.com/admin`
* открывает только Tenant Admin
* tenant context обязателен

### Tenant public host

* `tenant-subdomain.yourdomain.com`
* открывает публичный сайт tenant
* tenant context обязателен

### Unknown host

* 404
* либо marketing site fallback
* либо “domain not connected”

---

# 5.5. Реализовать отдельный Platform Dashboard

Нужно сделать **отдельный dashboard для Platform Console**.

## KPI верхнего ряда

* всего клиентов;
* active tenants;
* trial tenants;
* suspended tenants;
* новые клиенты за 7 дней;
* новые клиенты за 30 дней;
* tenants без опубликованного сайта;
* tenants без завершённого onboarding.

## Операционные виджеты

* последние созданные клиенты;
* клиенты без транспорта;
* клиенты без домена;
* клиенты с ошибками интеграций;
* клиенты с истёкшим trial;
* клиенты без branding;
* клиенты с незавершённым onboarding;
* клиенты без опубликованных страниц.

## Growth / business widgets

* распределение по планам;
* usage by plan;
* top active tenants;
* onboarding completion rate;
* published site rate;
* future-ready блок под MRR / billing.

## Важно

Platform dashboard должен работать **только по platform-level и aggregated tenant data**, без случайного использования tenant admin widgets.

---

# 5.6. Сделать отдельный Tenant Dashboard

Если он уже существует — убедиться, что он tenant-only и не тянет platform data.

Должен содержать:

* leads today;
* leads in progress;
* active bookings;
* catalog count;
* rental units count;
* missing SEO;
* website status;
* onboarding status.

---

# 5.7. Разделить навигацию

## Platform Console navigation

* Dashboard
* Clients
* Domains
* Plans
* Template Presets
* Platform Users
* Billing
* Feature Flags
* Platform Settings
* Support / Impersonation
* System Audit
* Integrations Health

## Tenant Admin navigation

* Dashboard
* Website / CMS
* Catalog
* Rental Units
* Leads
* Customers
* Bookings
* Pricing
* Branding
* SEO
* Reviews
* FAQ
* Team
* Settings

### Требование

Меню не должно строиться на основе одного и того же набора ресурсов с простым скрытием.
Панели должны регистрировать **свои собственные ресурсы и страницы**.

---

# 5.8. Подготовить Platform Website

Нужно заложить публичный слой платформы.

## Назначение

Продажа платформы.

## Контент

* Hero
* Возможности платформы
* Для каких бизнесов подходит
* Как работает
* Тарифы
* Кейсы / use cases
* FAQ
* Форма заявки
* Контакты

## Важно

Пока можно сделать это как обычные Laravel routes + Blade views, не как Filament panel.

### Желательно

Сразу предусмотреть:

* отдельный route group;
* platform marketing layout;
* возможность управлять этим контентом через Platform Console в будущем.

---

# 5.9. Зафиксировать Access Matrix

Нужно явно реализовать и задокументировать матрицу доступа.

## Platform roles

* `platform_owner`
* `platform_admin`
* `support_manager`

## Tenant roles

* `tenant_owner`
* `tenant_admin`
* `booking_manager`
* `fleet_manager`
* `content_manager`
* `operator`

## Проверить и реализовать

### Platform roles могут:

* входить в Platform Console;
* управлять tenants;
* управлять domains/plans/templates/settings;
* видеть platform dashboard.

### Tenant roles могут:

* входить только в Tenant Admin своего tenant;
* видеть только tenant-scoped данные;
* не видеть platform resources.

### Support manager

Не должен автоматически видеть tenant admin без явной support policy.

---

# 5.10. Добавить защиту от утечки данных

Это критически важно.

## Нужно гарантировать:

* tenant user не может увидеть других tenants;
* tenant user не может попасть в Platform Console;
* tenant user не может открыть platform routes;
* tenant user не может через guessed URL открыть platform resources;
* platform user не должен случайно работать в tenant context без явного перехода.

### Проверить:

* navigation;
* direct URLs;
* policies;
* global scopes;
* resources;
* actions;
* exports;
* widgets;
* search;
* global search;
* relations;
* dashboards.

---

# 5.11. Support / Impersonation пока только заложить, но не делать опасно

Если уже есть поддержка или планируется, то:

* не давать неявный доступ platform users в tenant admin;
* support access должен быть явно инициируемым;
* всё должно логироваться;
* пока можно оставить как placeholder page / policy contract.

---

# 6. Требования к реализации

---

## 6.1. Не ломать текущую multi-tenant модель

Существующий tenant context, tenant scoping и tenant ownership checks должны остаться рабочими.

---

## 6.2. Не ломать public booking flow

Маршруты:

* `/booking`
* `/booking/moto/{slug}`
* `/checkout`
* `/thank-you`

должны продолжить работать в tenant public zone.

---

## 6.3. Не ломать текущую CMS и tenant admin

Нужно не переписать всё заново, а **аккуратно разделить ответственность**.

---

## 6.4. Не использовать только “permissions hiding”

Нужны реальные технические барьеры:

* panel separation;
* host-based rules;
* access middleware;
* policies.

---

# 7. Предлагаемая структура файлов

Нужно прийти примерно к такой структуре.

## Panel providers

* `app/Providers/Filament/PlatformPanelProvider.php`
* `app/Providers/Filament/AdminPanelProvider.php` — сделать tenant-only
  или переименовать в:
* `TenantPanelProvider.php`

## Middleware / services

* `app/Http/Middleware/ResolveTenantFromDomain.php`
* `app/Http/Middleware/EnsurePlatformAccess.php`
* `app/Http/Middleware/EnsureTenantAccess.php`
* `app/Services/TenantResolver.php`
* `app/Services/CurrentTenantManager.php`

## Platform resources/pages/widgets

* `app/Filament/Platform/Resources/...`
* `app/Filament/Platform/Pages/...`
* `app/Filament/Platform/Widgets/...`

## Tenant resources/pages/widgets

* `app/Filament/Resources/...`
  или лучше привести к:
* `app/Filament/Tenant/Resources/...`
* `app/Filament/Tenant/Pages/...`
* `app/Filament/Tenant/Widgets/...`

## Marketing site

* `routes/web.php`
* `app/Http/Controllers/PlatformMarketing/...`
* `resources/views/platform/...`

---

# 8. Что нужно реализовать в Platform Console в первой версии

Минимально:

* Platform Dashboard
* TenantResource
* TenantDomainResource
* PlanResource
* TemplatePresetResource
* PlatformUser management
* Platform Settings
* Clients onboarding overview

### TenantResource

Поля:

* name
* slug
* status
* timezone
* locale
* country
* currency
* plan
* owner
* onboarding status
* published site status
* created_at

### Tenant list filters

* active
* trial
* suspended
* onboarding incomplete
* no domain
* no site published
* no vehicles
* by plan

---

# 9. Что оставить в Tenant Admin

Минимально:

* Dashboard
* Pages / CMS
* Motorcycles / VehicleModels
* RentalUnits
* Leads
* Customers
* Bookings
* PricingRules
* Branding / TenantSettings
* SEO
* Reviews
* FAQ
* Team
* Settings

---

# 10. Platform Website — первая версия

Нужно заложить продающий сайт платформы.

## Страницы

* `/`
* `/features`
* `/pricing`
* `/for-moto-rental`
* `/for-car-rental`
* `/faq`
* `/contact`

## Контент

* оффер платформы;
* преимущества;
* screenshots/features;
* pricing;
* CTA;
* demo request form.

---

# 11. Критерии приёмки

Задача считается выполненной только если:

## Архитектура

* есть отдельная Platform Console panel;
* Tenant Admin больше не содержит platform-level ресурсы;
* platform-level ресурсы не доступны через tenant admin;
* tenant-level ресурсы не смешиваются с platform navigation.

## Безопасность

* tenant user не может зайти в Platform Console;
* tenant user не может увидеть других клиентов;
* platform user не получает автоматический tenant access;
* access rules работают не только в UI, но и по прямым URL.

## UX

* Platform Console имеет свой dashboard;
* Tenant Admin имеет свою отдельную навигацию;
* onboarding клиента больше не живёт в клиентской админке.

## Продукт

* есть базовый Platform Website для продажи платформы;
* есть понятное разделение:

  * Platform Website
  * Platform Console
  * Tenant Admin
  * Tenant Public Site

---

# 12. Формат работы для Cursor

Сначала:

1. проанализируй текущую структуру панелей, навигации и middleware;
2. перечисли, какие ресурсы сейчас ошибочно смешаны;
3. предложи точный план разнесения по зонам;
4. затем внеси изменения поэтапно;
5. после изменений кратко опиши:

   * какие файлы созданы,
   * какие перемещены,
   * какие ресурсы теперь platform-only,
   * какие tenant-only,
   * как защищён доступ.

---

# 13. Жёсткие ограничения

* не ломать текущую multi-tenant архитектуру;
* не ломать существующий booking flow;
* не ломать tenant CMS;
* не делать “просто скрытие меню” как основное решение;
* не делать платформенную и клиентскую панель в одном navigation tree;
* не допустить ни одного пути, по которому tenant user может увидеть platform-level данные.

---

# 14. Дополнительные улучшения, если уместно

Если получится аккуратно и без слома архитектуры:

* переименовать текущую tenant panel так, чтобы кодовая база была чище (`TenantPanelProvider`);
* добавить верхний indicator текущего tenant в Tenant Admin;
* добавить onboarding completion badge в Platform Console;
* добавить platform health widget;
* подготовить placeholder под support impersonation.

---






План **сильный**. Это уже не “направление”, а **хороший execution-plan на разделение Platform / Tenant**.

Главное: ты правильно поставил **Фазу A как обязательный фундамент безопасности**. Это самый важный кусок во всём плане.

## Что особенно хорошо

### 1. Ты нашёл реально опасные места, а не косметику

Самые критичные расхождения пойманы правильно:

* `User::canAccessPanel()` не различает панели;
* `EnsureTenantMembership` с bypass для `super_admin` реально ломает boundary;
* `Platform Website` сейчас архитектурно заблокирован `EnsureTenantContext`;
* роли не соответствуют новой матрице;
* Platform Console пока недособрана по MVP.

Это ровно те проблемы, которые и надо чинить в первую очередь.

### 2. Фаза A собрана правильно

Очень хороший порядок:

* сначала panel access,
* потом middleware,
* потом убрать super_admin bypass,
* потом уже новая ролевая модель.

Это правильная последовательность.

### 3. Ты не пытаешься “решить всё одним махом”

Отдельно:

* безопасность,
* затем Platform Console MVP,
* затем tenant cleanup,
* затем marketing site,
* затем документация и приёмка.

Это зрелый подход.

---

# Мой вердикт

## Да, этот план можно брать в работу

И я бы даже сказал:
**это уже хороший кандидат на “implementation prompt + rollout checklist”**.

Но я бы внёс **несколько важных уточнений**, чтобы не допустить поломки доступа и регрессий.

---

# Что я бы усилил

## 1. В Фазе A добавь отдельный пункт: panel-specific auth policy

Сейчас у тебя есть:

* `canAccessPanel`
* `EnsurePlatformAccess`
* `EnsureTenantMembership`

Это хорошо, но я бы прямо зафиксировал правило:

### Источник истины для доступа

* **доступ в panel определяется и в `canAccessPanel()`, и middleware**
* middleware не заменяет `canAccessPanel()`
* `canAccessPanel()` не заменяет middleware

То есть:

* `canAccessPanel()` — первый барьер;
* middleware — второй барьер;
* policies/scopes — третий барьер.

Это стоит записать явно, чтобы Cursor не “оптимизировал” один слой в пользу другого.

---

## 2. В A1 важно разделить доступ в tenant panel по membership + current tenant host

Сейчас мысль есть, но я бы сделал её совсем явной:

### Tenant panel access rule

Пользователь может зайти в tenant panel только если одновременно:

* есть `currentTenant()`;
* есть membership в `tenant_user` для этого tenant;
* membership активен;
* tenant role входит в разрешённый набор tenant roles;
* пользователь не заблокирован.

Это лучше прямо прописать как контракт.

---

## 3. В A3 после удаления bypass нужен migration impact note

Очень важно: после того как уберёшь `super_admin` bypass, часть доступов может “сломаться” у текущих пользователей.

Я бы прямо добавил:

### Migration impact

После удаления bypass:

* все platform staff теряют доступ в tenant admin без membership;
* если кому-то нужен доступ в конкретный tenant, membership должен быть назначен явно;
* до реализации impersonation никаких скрытых исключений быть не должно.

Это поможет избежать паники “почему админ больше не заходит”.

---

## 4. В A4 нужна стратегия soft-transition ролей

Ты правильно хочешь перейти от:

* `super_admin`
* `admin`

к:

* `platform_owner`
* `platform_admin`
* `support_manager`
* tenant roles

Но я бы добавил безопасный переход:

### Transitional role mapping

На этапе миграции:

* `super_admin` -> `platform_owner`
* `admin` не маппить автоматически в tenant admin без контекста
* tenant membership roles назначать отдельно

И обязательно:

* не оставлять legacy role checks в production коде после миграции.

---

## 5. В B1 Platform Dashboard лучше делать только на platform models + safe aggregates

Это ты уже подразумеваешь, но стоит записать жёстче.

### Нельзя

* напрямую reuse tenant widgets;
* строить dashboard на tenant-scoped resources без явной агрегации.

### Нужно

* агрегаты через platform-safe queries;
* counts через `Tenant`, `TenantDomain`, `Plan`, onboarding flags, publish status, usage aggregates.

Иначе будет соблазн “просто переиспользовать tenant widget”.

---

## 6. В B2 для Platform Users лучше сразу отделить query scope

Ты пишешь:

> User с platform-ролями (отдельный ресурс или scoped query)

Я бы здесь уже зафиксировал:
**отдельный PlatformUserResource со scoped query на platform roles**.

Потому что “или” потом превращается в размытость.

---

## 7. В C1 я бы выбрал один путь, не оставлял “либо”

Ты пишешь:

* либо сузить discovery,
* либо перенести tenant-ресурсы в `app/Filament/Tenant/...`

Я бы рекомендовал сразу утвердить:

## Рекомендация

Постепенно привести tenant-ресурсы к:

* `app/Filament/Tenant/Resources/...`
* `app/Filament/Tenant/Pages/...`
* `app/Filament/Tenant/Widgets/...`

А для platform:

* `app/Filament/Platform/...`

Да, это чуть больше работы, но зато кодовая база станет сильно чище и безопаснее.

---

## 8. В D1 надо отдельно прописать, что marketing routes не должны зависеть от tenant context

Это очень важно.

Я бы прямо добавил:

### Platform Website rules

* marketing routes не используют `EnsureTenantContext`;
* marketing routes работают на `platform_host`;
* marketing site не зависит от tenant resolution;
* tenant public site остаётся tenant-scoped.

Это нужно прямо зафиксировать, чтобы потом никто не добавил туда tenant middleware “по привычке”.

---

## 9. В D2 выбери один вариант unknown host

Сейчас у тебя:

* 404 / domain not connected / fallback на маркетинг

Я бы не оставлял 3 варианта.

### Моя рекомендация

Для неизвестного host:

* если host выглядит как tenant/custom domain route и не найден — **`domain not connected` page**
* если это platform root host — **marketing site**
* если вообще мусорный host — **404**

Но в текущем плане лучше хотя бы выбрать одно поведение для первой версии.

---

## 10. В E добавь обязательный security acceptance checklist

Сейчас у тебя уже есть идея чеклиста, но я бы сделал прямо обязательные тест-кейсы:

### Обязательно проверить

* tenant user не заходит на platform host;
* tenant user не открывает platform resources по прямому URL;
* platform user без membership не открывает tenant admin;
* tenant user A не открывает tenant B;
* global search не светит чужие tenant records;
* exports не утекают cross-tenant;
* widgets не показывают чужие агрегаты.

---

# Что я бы ещё добавил как небольшую фазу

## A0 — Freeze текущего смешанного доступа

Перед началом Фазы A я бы добавил мини-шаг:

### A0

* зафиксировать текущие panel routes;
* снять список доступных ресурсов в Platform и Tenant;
* сделать backup/снимок current role mappings;
* перечислить всех пользователей с `super_admin` и их реальные сценарии доступа.

Это поможет безопасно пройти A3/A4.

---

# Моя оценка по фазам

## Фаза A

Очень сильная. Самая важная. Одобряю.

## Фаза B

Хорошая и реалистичная. Достаточно для MVP Platform Console.

## Фаза C

Правильная, особенно часть про discovery и прямые URL.

## Фаза D

Очень важная, потому что без Platform Website продуктово платформа “немая”.

## Фаза E

Обязательна. Хорошо, что ты её не забыл.

---

# Итоговая оценка плана

## **9.7/10**

Это уже реально сильный план.

Если коротко:

* по безопасности — правильно,
* по архитектуре — правильно,
* по продукту — правильно,
* по rollout order — правильно.

---

# Как бы я утвердил это в одну фразу

**Да, этот план можно использовать как основной implementation plan на разделение Platform Console, Tenant Admin и Platform Website.**

---

# Что делать следующим шагом

Я бы теперь уже не обсуждал идею дальше, а переходил к двум практическим артефактам:

## 1. Execution prompt для Cursor

По сути у тебя он уже почти готов.

## 2. Access matrix doc

Прям отдельной таблицей:

* role
* zone
* allowed
* denied
* notes

Это сильно упростит проверку после внедрения.

Если хочешь, я следующим сообщением соберу тебе **ультра-жёсткую техническую версию этого ТЗ для Cursor**, уже без общих слов — только:

* panel providers,
* middleware,
* role mapping,
* resource relocation,
* route groups,
* acceptance checks.
