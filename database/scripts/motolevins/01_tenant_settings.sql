-- Moto Levins: upsert tenant_settings for public site + contacts.
-- Ручной SQL (не `php artisan migrate`); выполнять в клиенте БД.
-- Requires: row in `tenants` with slug = 'motolevins'.
-- MySQL 8+ / MariaDB 10.5+ (INSERT ... ON DUPLICATE KEY UPDATE).

SET @tenant_id := (SELECT id FROM tenants WHERE slug = 'motolevins' LIMIT 1);

-- If @tenant_id is NULL, stop here and create the tenant first (Platform Console or seeders).

INSERT INTO tenant_settings (tenant_id, `group`, `key`, value, type, created_at, updated_at) VALUES
(@tenant_id, 'general', 'site_name', 'Moto Levins', 'string', NOW(), NOW()),
(@tenant_id, 'general', 'domain', 'https://motolevins.rentbase.su', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'phone', '+7 (913) 060-86-89', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'phone_alt', '', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'whatsapp', '79130608689', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'telegram', 'motolevins', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'email', '', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'address', '', 'string', NOW(), NOW()),
(@tenant_id, 'contacts', 'hours', '', 'string', NOW(), NOW()),
(@tenant_id, 'branding', 'primary_color', '#E85D04', 'string', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  value = VALUES(value),
  type = VALUES(type),
  updated_at = VALUES(updated_at);
