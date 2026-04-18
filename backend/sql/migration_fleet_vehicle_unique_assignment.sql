-- At most one active or pending driver may reference a given fleet_vehicle_id (database-enforced).
-- Requires MySQL 5.7.14+ or MariaDB 10.2+ (generated STORED column + unique index).
-- Run after migration_fleet_vehicles_drivers.sql. Idempotent for column/index.
--
-- 1) Clears duplicate assignments: for each vehicle used by multiple active/pending drivers,
--    keeps the row with the smallest id and sets fleet_vehicle_id to NULL on the others.
-- 2) Adds fleet_vehicle_active_key (generated) + UNIQUE so new duplicates are rejected by the DB.

-- ——— Resolve existing duplicates (safe to run multiple times) ———
-- Keep lowest id per vehicle; clear vehicle on other active/pending rows sharing that car.
UPDATE fleet_drivers d
INNER JOIN (
  SELECT fleet_vehicle_id, MIN(id) AS keep_id
  FROM fleet_drivers
  WHERE status IN ('active', 'pending')
    AND fleet_vehicle_id IS NOT NULL
  GROUP BY fleet_vehicle_id
  HAVING COUNT(*) > 1
) dup ON dup.fleet_vehicle_id = d.fleet_vehicle_id
SET d.fleet_vehicle_id = NULL
WHERE d.status IN ('active', 'pending')
  AND d.id != dup.keep_id;

-- ——— Add generated column + unique index if missing ———
DROP PROCEDURE IF EXISTS vpride_fleet_vehicle_unique_assignment;

DELIMITER $$

CREATE PROCEDURE vpride_fleet_vehicle_unique_assignment()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fleet_drivers'
      AND COLUMN_NAME = 'fleet_vehicle_active_key'
  ) THEN
    ALTER TABLE fleet_drivers
      ADD COLUMN fleet_vehicle_active_key BIGINT UNSIGNED NULL
      GENERATED ALWAYS AS (
        CASE
          WHEN `status` IN ('active', 'pending') AND `fleet_vehicle_id` IS NOT NULL
          THEN `fleet_vehicle_id`
          ELSE NULL
        END
      ) STORED;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fleet_drivers'
      AND INDEX_NAME = 'uq_fleet_drivers_active_vehicle'
  ) THEN
    ALTER TABLE fleet_drivers
      ADD UNIQUE KEY uq_fleet_drivers_active_vehicle (fleet_vehicle_active_key);
  END IF;
END$$

DELIMITER ;

CALL vpride_fleet_vehicle_unique_assignment();

DROP PROCEDURE IF EXISTS vpride_fleet_vehicle_unique_assignment;
