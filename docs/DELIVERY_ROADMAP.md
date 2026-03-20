# Delivery Roadmap — Rental Platform OS

## Phase 1 — Platform Foundation ✅

**Цель:** Multi-tenancy, создание tenant, subdomain routing, разделение админок.

**Done criteria:**
- [x] Модели Tenant, TenantDomain, Plan, TemplatePreset
- [x] tenant_id во всех tenant-scoped таблицах
- [x] TenantResolver, CurrentTenantManager, ResolveTenantFromDomain
- [x] users + tenant_user
- [x] platform_settings, tenant_settings
- [x] BelongsToTenant trait и global scoping
- [x] Platform Admin и Tenant Admin разделены
- [x] Миграция Moto Levins в tenancy

**Rollback:** Backup БД до шага «tenant_id NOT NULL». При проблемах — откат миграций, убрать tenant_id из queries.

---

## Phase 1.5 — Tenant Safety Pass ✅

**Цель:** Жёсткая изоляция tenant.

**Done criteria:**
- [x] Jobs восстанавливают tenant context (SendLeadTelegramNotification, SendBookingTelegramNotification)
- [x] Policies проверяют tenant ownership (ChecksTenantOwnership)
- [x] Queries tenant-scoped через BelongsToTenant

---

## Phase 2 — CMS, Branding, Template Presets ✅

**Цель:** Template contract, брендинг, контент.

**Done criteria:**
- [x] TemplateCloningService, клонирование при создании tenant
- [x] Tenant branding (logo, primary_color) в tenant_settings
- [x] Reserved slugs: booking, checkout, thank-you, admin, platform

---

## Phase 2.5 — Onboarding Wizard ✅

**Цель:** Быстрый time-to-value для нового tenant.

**Done criteria:**
- [x] OnboardingWizard page: Create → Template → Branding → Contacts → Create
- [x] Создание tenant, клонирование шаблона, домен, настройки

---

## Phase 3 — Users, RBAC, CRM, Customers ✅

**Цель:** Роли tenant, lead pipeline, customers.

**Done criteria:**
- [x] tenant_user с ролями
- [x] EnsureTenantMembership middleware
- [x] Customer model, customer_id в leads и bookings
- [x] Lead → Customer конверсия (подготовка)

---

## Phase 3.5 — Tenant Dashboard ✅

**Цель:** Ощущение продукта для tenant owner.

**Done criteria:**
- [x] StatsOverviewWidget: Leads today, New leads, In progress, Motorcycles, Missing SEO

---

## Phase 4 — Booking Engine & Availability ✅

**Цель:** RentalUnit, availability, bookings, pricing, add-ons.

**Done criteria:**
- [x] availability_calendar, AvailabilityService
- [x] Bookings: rental_unit_id, pricing_snapshot_json, deposit_amount, payment_status
- [x] pricing_rules, addons, booking_addons
- [x] PricingService
- [x] Public booking flow: /booking, /booking/moto/{slug}, /checkout, /thank-you

---

## Phase 5 — Operations (будущее)

**Цель:** Договоры, инспекции, платежи.

- document_templates
- inspections (check-in/check-out)
- payments
- damage logs

---

## Phase 6 — SaaS Scale (будущее)

**Цель:** Platform billing, custom domains, API.

- subscriptions, invoices
- Custom domains, DNS verification, SSL
- Template marketplace
- REST/GraphQL API

---

## Dependencies

```
Phase 1 → Phase 1.5 → Phase 2 → Phase 2.5
                ↓
            Phase 3 → Phase 3.5
                ↓
            Phase 4 → Phase 5 → Phase 6
```

## Risks & Mitigation

| Риск | Митигация |
|------|-----------|
| Tenancy не везде | Phase 1.5 pass, policy checks |
| Platform/Tenant admin смешиваются | Отдельные panels, domain routing |
| Template chaos | Контракт шаблона, cloning strategy |
| Booking в CMS | Отдельные слои, сервисы |
