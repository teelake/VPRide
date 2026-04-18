-- Console-created bookings: lets drivers confirm/adjust final fare at trip completion.
-- Run after migration_dispatch_drivers.sql. Idempotent.

DROP PROCEDURE IF EXISTS vpride_console_booking_add_column;

DELIMITER $$

CREATE PROCEDURE vpride_console_booking_add_column(
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

CALL vpride_console_booking_add_column(
  'rides',
  'console_booking',
  'TINYINT(1) NOT NULL DEFAULT 0'
);

DROP PROCEDURE IF EXISTS vpride_console_booking_add_column;
