-- Rider booking v2: fixed distance pricing, scheduled pickup, round-trip legs, ratings.
-- Run on your VP Ride DB after migration_sos_promos_loyalty.sql (platform_promo_settings + rides exist).
--
-- Idempotent: safe to run again; skips columns and FK that already exist.
-- Requires permission to CREATE ROUTINE (most MySQL/MariaDB installs allow it).

DROP PROCEDURE IF EXISTS vpride_booking_v2_add_column;

DELIMITER $$

CREATE PROCEDURE vpride_booking_v2_add_column(
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

CALL vpride_booking_v2_add_column(
  'platform_promo_settings',
  'pricing_base_fare',
  'DECIMAL(12, 2) NOT NULL DEFAULT 500.00 AFTER default_ride_estimate'
);
CALL vpride_booking_v2_add_column(
  'platform_promo_settings',
  'pricing_per_km',
  'DECIMAL(12, 4) NOT NULL DEFAULT 350.0000 AFTER pricing_base_fare'
);
CALL vpride_booking_v2_add_column(
  'platform_promo_settings',
  'pricing_minimum_fare',
  'DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER pricing_per_km'
);
CALL vpride_booking_v2_add_column(
  'platform_promo_settings',
  'advance_booking_max_days',
  'SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER loyalty_reward_promotion_id'
);

CALL vpride_booking_v2_add_column(
  'rides',
  'scheduled_pickup_at',
  'DATETIME NULL AFTER updated_at'
);
CALL vpride_booking_v2_add_column(
  'rides',
  'trip_leg',
  'ENUM(\'single\', \'outbound\', \'return\') NOT NULL DEFAULT \'single\' AFTER scheduled_pickup_at'
);
CALL vpride_booking_v2_add_column(
  'rides',
  'companion_ride_id',
  'BIGINT UNSIGNED NULL AFTER trip_leg'
);
CALL vpride_booking_v2_add_column(
  'rides',
  'distance_km',
  'DECIMAL(10, 5) NULL AFTER companion_ride_id'
);
CALL vpride_booking_v2_add_column(
  'rides',
  'rating_stars',
  'TINYINT UNSIGNED NULL AFTER distance_km'
);
CALL vpride_booking_v2_add_column(
  'rides',
  'feedback_text',
  'VARCHAR(2000) NULL AFTER rating_stars'
);
CALL vpride_booking_v2_add_column(
  'rides',
  'rated_at',
  'DATETIME NULL AFTER feedback_text'
);

DROP PROCEDURE IF EXISTS vpride_booking_v2_add_column;

-- Self-referential FK (optional on some hosts — skip if this errors)
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rides'
    AND CONSTRAINT_NAME = 'fk_rides_companion'
);
SET @sql_fk = IF(
  @fk_exists > 0,
  'SELECT 1',
  'ALTER TABLE rides ADD CONSTRAINT fk_rides_companion FOREIGN KEY (companion_ride_id) REFERENCES rides (id) ON DELETE SET NULL'
);
PREPARE fk_stmt FROM @sql_fk;
EXECUTE fk_stmt;
DEALLOCATE PREPARE fk_stmt;
