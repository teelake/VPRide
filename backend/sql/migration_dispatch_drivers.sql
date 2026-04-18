-- Dispatch: link fleet drivers to app accounts, driver availability, assignment metadata, refusals.
-- Run after migration_fleet_vehicles_drivers.sql and migration_rides / SOS ride columns.
-- Idempotent (safe to re-run).

DROP PROCEDURE IF EXISTS vpride_dispatch_add_column;

DELIMITER $$

CREATE PROCEDURE vpride_dispatch_add_column(
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

CALL vpride_dispatch_add_column(
  'fleet_drivers',
  'rider_user_id',
  'BIGINT UNSIGNED NULL UNIQUE AFTER id'
);

SET @fk_fd_rider = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fleet_drivers'
    AND CONSTRAINT_NAME = 'fk_fleet_drivers_rider_user'
);
SET @sql_fd_fk = IF(
  @fk_fd_rider > 0,
  'SELECT 1',
  'ALTER TABLE fleet_drivers ADD CONSTRAINT fk_fleet_drivers_rider_user FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE SET NULL'
);
PREPARE fd_fk_stmt FROM @sql_fd_fk;
EXECUTE fd_fk_stmt;
DEALLOCATE PREPARE fd_fk_stmt;

CREATE TABLE IF NOT EXISTS driver_availability (
  rider_user_id BIGINT UNSIGNED PRIMARY KEY,
  status ENUM('offline', 'online', 'busy') NOT NULL DEFAULT 'offline',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_da_rider FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ride_driver_refusals (
  ride_id BIGINT UNSIGNED NOT NULL,
  driver_rider_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ride_id, driver_rider_user_id),
  CONSTRAINT fk_rdr_ride FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE CASCADE,
  CONSTRAINT fk_rdr_driver FOREIGN KEY (driver_rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL vpride_dispatch_add_column(
  'rides',
  'assign_source',
  'ENUM(\'none\', \'auto\', \'manual\') NOT NULL DEFAULT \'none\' AFTER status'
);

DROP PROCEDURE IF EXISTS vpride_dispatch_add_column;

-- RBAC: dispatch & manual booking
INSERT IGNORE INTO admin_permissions (slug, label, category) VALUES
('rides.dispatch', 'Assign drivers & dispatch rides', 'rides'),
('rides.create_manual', 'Create manual bookings from console', 'rides');

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'dispatcher'
  AND p.slug IN ('rides.dispatch', 'rides.create_manual');
