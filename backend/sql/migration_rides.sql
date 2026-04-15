-- Rides table for POST /api/v1/rides and admin dashboard.
-- In phpMyAdmin: select your real database (e.g. u232647434_vpride), then run this.
-- Do not use "USE vpride" on shared hosts unless that is your actual DB name.
-- Requires table rider_users (see migration_rider_auth.sql).

CREATE TABLE IF NOT EXISTS rides (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM(
    'requested',
    'accepted',
    'in_progress',
    'completed',
    'cancelled'
  ) NOT NULL DEFAULT 'requested',
  pickup_lat DECIMAL(10, 7) NOT NULL,
  pickup_lng DECIMAL(10, 7) NOT NULL,
  pickup_address VARCHAR(512) NULL,
  dropoff_lat DECIMAL(10, 7) NULL,
  dropoff_lng DECIMAL(10, 7) NULL,
  dropoff_address VARCHAR(512) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rides_rider FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE,
  INDEX idx_rides_rider (rider_user_id),
  INDEX idx_rides_status (status),
  INDEX idx_rides_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
