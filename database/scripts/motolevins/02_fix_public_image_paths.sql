-- =============================================================================
-- Когда запускать
-- -----------------------------------------------------------------------------
-- Только если в БД УЖЕ есть строки с СТАРЫМИ путями:
--   motorcycles.cover_image / bikes.image вида `bikes/....jpg`
--   отзывы без путей к аватарам в public
--
-- На пустом проде (нет байков, мотоциклов, отзывов) скрипт НИЧЕГО не изменит —
-- это нормально, запускать не обязательно. Новые данные заливайте сидерами
-- (BikeSeeder → Migration… → ReviewSeeder и т.д.) — пути сразу будут motolevins/*.
--
-- Это не миграция Laravel, а разовый SQL для MySQL/MariaDB.
-- =============================================================================

-- Normalize legacy cover paths to motolevins/* (matches public/images/motolevins/... on disk).
SET @tenant_id := (SELECT id FROM tenants WHERE slug = 'motolevins' LIMIT 1);

UPDATE motorcycles
SET cover_image = CONCAT('motolevins/', cover_image)
WHERE tenant_id = @tenant_id
  AND cover_image IS NOT NULL
  AND cover_image LIKE 'bikes/%'
  AND cover_image NOT LIKE 'motolevins/%';

UPDATE bikes
SET image = CONCAT('motolevins/', image)
WHERE tenant_id = @tenant_id
  AND image IS NOT NULL
  AND image LIKE 'bikes/%'
  AND image NOT LIKE 'motolevins/%';

UPDATE reviews
SET avatar = 'images/motolevins/avatars/avatar-1.png'
WHERE tenant_id = @tenant_id AND name = 'Алексей М.' AND (avatar IS NULL OR avatar = '' OR avatar NOT LIKE '%avatar-1%');

UPDATE reviews
SET avatar = 'images/motolevins/avatars/avatar-2.png'
WHERE tenant_id = @tenant_id AND name = 'Игорь С.' AND (avatar IS NULL OR avatar = '' OR avatar NOT LIKE '%avatar-2%');

UPDATE reviews
SET avatar = 'images/motolevins/avatars/avatar-3.png'
WHERE tenant_id = @tenant_id AND name = 'Анна В.' AND (avatar IS NULL OR avatar = '' OR avatar NOT LIKE '%avatar-3%');
