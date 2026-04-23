-- Client brief: phone-first console riders, rider "swap driver" limits, and audit of rider refusals.
-- Safe to run on existing databases. Requires MySQL 5.7+ (information_schema + prepared statement).

-- 1) rider_users.phone (optional; used for console/SMS customers)
SET @s := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rider_users'
    AND COLUMN_NAME = 'phone'
);
SET @q := IF(
  @s = 0,
  'ALTER TABLE rider_users ADD COLUMN phone VARCHAR(32) NULL AFTER email, ADD INDEX idx_rider_users_phone (phone)',
  'SELECT 1'
);
PREPARE stmt FROM @q;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) When a rider requests a different driver, we record the rejected driver and enforce per-ride limits.
CREATE TABLE IF NOT EXISTS ride_rider_rejects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ride_id BIGINT UNSIGNED NOT NULL,
  rejected_driver_rider_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ride_rider_rejects_ride (ride_id),
  INDEX idx_ride_rider_rejects_driver (rejected_driver_rider_user_id),
  CONSTRAINT fk_ride_rider_rejects_ride FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
