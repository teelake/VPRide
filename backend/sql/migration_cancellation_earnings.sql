-- Rider cancellation metadata, driver earnings snapshot, per-driver earnings override.
-- Idempotent. Run after core rides / fleet_drivers migrations.

DROP PROCEDURE IF EXISTS vpride_ce_add_column;

DELIMITER $$

CREATE PROCEDURE vpride_ce_add_column(
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

CALL vpride_ce_add_column(
  'rides',
  'cancellation_fee_amount',
  'DECIMAL(12,2) NULL'
);

CALL vpride_ce_add_column(
  'rides',
  'cancelled_by',
  'VARCHAR(16) NULL'
);

CALL vpride_ce_add_column(
  'rides',
  'cancelled_at',
  'DATETIME NULL'
);

CALL vpride_ce_add_column(
  'rides',
  'driver_earnings_amount',
  'DECIMAL(12,2) NULL'
);

CALL vpride_ce_add_column(
  'rides',
  'driver_earnings_percent_applied',
  'DECIMAL(5,2) NULL'
);

CALL vpride_ce_add_column(
  'fleet_drivers',
  'earnings_percent_override',
  'DECIMAL(5,2) NULL'
);

DROP PROCEDURE IF EXISTS vpride_ce_add_column;
