-- At most one active or pending driver may reference a given fleet_vehicle_id (database-enforced).
-- Requires MySQL 5.7.14+ or MariaDB 10.2+ (generated STORED column + unique index).
-- Run after migration_fleet_vehicles_drivers.sql. Idempotent: safe to run more than once.
--
-- This file does NOT use stored procedures or DEFINER, so it works on:
--   * phpMyAdmin and other importers that split on `;` (no DELIMITER needed)
--   * shared hosting without CREATE ROUTINE or SET USER
--
-- 1) Clears duplicate assignments: for each vehicle used by multiple active/pending drivers,
--    keeps the row with the smallest id and sets fleet_vehicle_id to NULL on the others.
-- 2) Adds fleet_vehicle_active_key (generated) + UNIQUE so new duplicates are rejected by the DB.

-- ——— 1) Resolve existing duplicates (safe to run multiple times) ———
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

-- ——— 2) Add generated column (only if missing) ———
SET @vpride_sql := (
  SELECT IF(
    (
      SELECT COUNT(*)
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'fleet_drivers'
        AND COLUMN_NAME = 'fleet_vehicle_active_key'
    ) = 0,
    'ALTER TABLE fleet_drivers ADD COLUMN fleet_vehicle_active_key BIGINT UNSIGNED GENERATED ALWAYS AS ( CASE WHEN `status` IN (''active'', ''pending'') AND `fleet_vehicle_id` IS NOT NULL THEN `fleet_vehicle_id` ELSE NULL END ) STORED',
    'SELECT 1'
  )
);
PREPARE vpride_stmt FROM @vpride_sql;
EXECUTE vpride_stmt;
DEALLOCATE PREPARE vpride_stmt;

-- ——— 3) Add unique index (only if missing) ———
SET @vpride_sql := (
  SELECT IF(
    (
      SELECT COUNT(*)
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'fleet_drivers'
        AND INDEX_NAME = 'uq_fleet_drivers_active_vehicle'
    ) = 0,
    'ALTER TABLE fleet_drivers ADD UNIQUE KEY uq_fleet_drivers_active_vehicle (fleet_vehicle_active_key)',
    'SELECT 1'
  )
);
PREPARE vpride_stmt FROM @vpride_sql;
EXECUTE vpride_stmt;
DEALLOCATE PREPARE vpride_stmt;
