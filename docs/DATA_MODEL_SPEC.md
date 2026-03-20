# Data Model Spec — Rental Platform OS

## Platform-level tables

| Таблица | Описание |
|---------|----------|
| tenants | id, name, slug, status, timezone, locale, country, currency, plan_id, owner_user_id, template_preset_id, created_at, updated_at |
| tenant_domains | id, tenant_id, host, type (subdomain/custom), is_primary, ssl_status, verification_status, dns_target |
| plans | id, name, slug, limits_json, features_json |
| tenant_user | tenant_id, user_id, role, status, invited_at |
| platform_settings | key, value |
| template_presets | id, name, slug, config_json, is_active |

## Tenant-level tables

Все имеют `tenant_id` NOT NULL, global scope `BelongsToTenant`.

| Таблица | Ключевые поля |
|---------|---------------|
| pages | tenant_id, name, slug, template, status |
| page_sections | tenant_id, page_id, section_key, section_type, data_json |
| categories | tenant_id, name, slug |
| motorcycles | tenant_id, name, slug, category_id, price_per_day, status |
| rental_units | tenant_id, motorcycle_id, status |
| leads | tenant_id, motorcycle_id, customer_id, source, status |
| customers | tenant_id, full_name, phone, email |
| reviews | tenant_id, motorcycle_id |
| faqs | tenant_id |
| bookings | tenant_id, motorcycle_id, rental_unit_id, customer_id, lead_id, booking_number, start_at, end_at, status |
| availability_calendar | rental_unit_id, starts_at, ends_at, status, booking_id |
| pricing_rules | tenant_id, motorcycle_id, rental_unit_id, rental_type, price, deposit |
| addons | tenant_id, name, type, price |
| booking_addons | booking_id, addon_id, quantity, price_snapshot |
| seo_meta | seoable_type, seoable_id (polymorphic) |
| redirects | tenant_id, from_url, to_url |
| tenant_settings | tenant_id, group, key, value |
| integrations | tenant_id |
| integration_logs | tenant_id, integration_id |
| form_configs | tenant_id, form_key |

## Unique constraints

| Сущность | Scope | Уникальность |
|----------|-------|--------------|
| pages.slug | tenant | unique(tenant_id, slug) |
| motorcycles.slug | tenant | unique(tenant_id, slug) |
| redirects.from_url | tenant | unique(tenant_id, from_url) |
| tenant.slug | global | unique |
| tenant_domains.host | global | unique |
| plans.slug | global | unique |
| template_presets.slug | global | unique |

## Relationships

- Tenant hasMany TenantDomain, hasMany TenantUser (through pivot)
- User belongsToMany Tenant (through tenant_user)
- Motorcycle hasMany RentalUnit, hasOne SeoMeta (morph)
- Page hasMany PageSection, hasOne SeoMeta (morph)
- Booking belongsTo Motorcycle, RentalUnit, Customer, Lead
- Lead belongsTo Motorcycle, Customer
- AvailabilityCalendar belongsTo RentalUnit, Booking

## Scoping rules

- Все tenant-scoped модели используют trait `BelongsToTenant`
- Scope: `where('tenant_id', currentTenant()->id)`
- Platform panel: без tenant scope, работает с tenants, plans, template_presets
- Tenant admin: всегда в tenant context

## Deletion rules

- Tenant cascade: tenant_domains, tenant_user, tenant_settings
- Tenant soft/hard delete: каскад на все tenant-scoped записи или архив
- Booking cancelled: освобождение availability_calendar
