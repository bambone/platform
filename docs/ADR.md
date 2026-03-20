# Architecture Decision Record (ADR)

## ADR-001: Shared DB + tenant_id

**Статус:** Принято

**Контекст:** Платформа должна поддерживать multi-tenancy. Возможны варианты: отдельная БД на tenant, отдельная схема на tenant, shared DB с tenant_id.

**Решение:** Использовать shared database с колонкой `tenant_id` во всех tenant-scoped таблицах. Глобальный scope `BelongsToTenant` автоматически фильтрует запросы по текущему tenant.

**Последствия:**
- Простота развёртывания и бэкапов
- Один пул соединений
- Риск утечки данных при ошибке в scope — митигируется Phase 1.5 (tenant safety pass) и проверкой policies

---

## ADR-002: users + tenant_user

**Статус:** Принято

**Контекст:** Нужна модель пользователей для multi-tenant. Варианты: tenant_id nullable в users, отдельная pivot tenant_user.

**Решение:** Глобальные `users` без tenant_id. Связь с tenant через pivot `tenant_user` (tenant_id, user_id, role, status). Один пользователь может иметь доступ к нескольким tenant.

**Последствия:**
- Нет риска «забытого» tenant_id при переключении контекста
- Platform support может быть назначен в несколько tenant
- Роли tenant (tenant_owner, booking_manager и т.д.) хранятся в pivot

---

## ADR-003: Platform Admin vs Tenant Admin

**Статус:** Принято

**Контекст:** Нужно разделить управление платформой и управление данными tenant.

**Решение:** Два Filament panel:
- **Platform Admin** — домен `platform.*`, путь `/platform`. Ресурсы: Tenant, Plan, TenantDomain. Не работает с tenant-scoped моделями.
- **Tenant Admin** — путь `/admin`. Работает только при разрешённом tenant. Все ресурсы tenant-scoped.

**Последствия:**
- Чёткое разделение ответственности
- Middleware `EnsureTenantContext` и `EnsureTenantMembership` для tenant admin

---

## ADR-004: Template cloning strategy

**Статус:** Принято

**Контекст:** Шаблоны сайтов должны применяться к новым tenant. Варианты: shared конфиг с override, полное клонирование.

**Решение:** При создании tenant выбранный template preset клонируется в страницы и секции tenant. После клонирования tenant живёт своей жизнью. Изменения глобального шаблона не влияют на уже созданные tenant.

**Последствия:**
- Изоляция контента tenant
- `TemplateCloningService` клонирует pages и page_sections с tenant_id

---

## ADR-005: Tenant resolution via domain

**Статус:** Принято

**Контекст:** Нужно определять tenant по входящему запросу.

**Решение:** Middleware `ResolveTenantFromDomain` по host находит tenant в `tenant_domains`. 4 режима: Platform host, Tenant admin, Tenant public, Unknown host. `CurrentTenantManager` хранит текущий tenant в request context.

**Последствия:**
- Tenant определяется один раз на request
- Jobs/notifications должны явно нести tenant context (tenant_id в payload)

---

## ADR-006: Marketing vs Booking vs Operations separation

**Статус:** Принято

**Контекст:** Платформа объединяет маркетинг (лендинг, каталог), бронирование и операции (договоры, инспекции, платежи).

**Решение:** Не смешивать слои в одной сущности. Маркетинг: pages, sections, motorcycles (catalog). Бронирование: bookings, availability_calendar, pricing_rules. Операции: inspections, payments, document_templates. Отдельные сервисы и ресурсы.

**Последствия:**
- Чёткая структура кода
- Возможность развивать слои независимо
