-- Trip tracking: driver last known location for rider ETA (optional columns on driver_availability).
-- Run after migration_dispatch_drivers.sql. Idempotent.

DROP PROCEDURE IF EXISTS vpride_trip_tracking_add_column;

DELIMITER $$

CREATE PROCEDURE vpride_trip_tracking_add_column(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @q = CONCAT(
      'ALTER TABLE `',
      REPLACE(p_table, '`', ''),
      '` ADD COLUMN `',
      REPLACE(p_column, '`', ''),
      '` ',
      p_definition
    );
    PREPARE stmt FROM @q;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$

DELIMITER ;

CALL vpride_trip_tracking_add_column(
  'driver_availability',
  'last_latitude',
  'DECIMAL(10, 7) NULL AFTER status'
);
CALL vpride_trip_tracking_add_column(
  'driver_availability',
  'last_longitude',
  'DECIMAL(10, 7) NULL AFTER last_latitude'
);
CALL vpride_trip_tracking_add_column(
  'driver_availability',
  'location_updated_at',
  'DATETIME NULL AFTER last_longitude'
);

DROP PROCEDURE IF EXISTS vpride_trip_tracking_add_column;
