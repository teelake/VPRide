-- SOS incidents, promotions, loyalty, ride pricing columns.
-- Run on your VP Ride database after backup. Select the correct DB in phpMyAdmin first.

CREATE TABLE IF NOT EXISTS platform_promo_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  currency_code CHAR(3) NOT NULL DEFAULT 'NGN',
  decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,
  default_ride_estimate DECIMAL(12, 2) NOT NULL DEFAULT 1500.00,
  promo_timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Lagos',
  loyalty_enabled TINYINT(1) NOT NULL DEFAULT 1,
  loyalty_trips_per_reward INT UNSIGNED NOT NULL DEFAULT 5,
  loyalty_reward_promotion_id BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_admin_id INT UNSIGNED NULL,
  CONSTRAINT fk_pps_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_promo_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS promotions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  kind ENUM('automatic', 'coupon') NOT NULL DEFAULT 'automatic',
  coupon_code VARCHAR(32) NULL,
  discount_kind ENUM('percent', 'fixed_amount') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10, 2) NOT NULL DEFAULT 0,
  max_discount_amount DECIMAL(12, 2) NULL,
  new_users_only TINYINT(1) NOT NULL DEFAULT 0,
  schedule_json JSON NULL,
  max_uses_per_rider INT UNSIGNED NULL,
  min_fare_amount DECIMAL(12, 2) NULL,
  priority INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_promotions_coupon_code (coupon_code),
  INDEX idx_promotions_active_kind (is_active, kind),
  INDEX idx_promotions_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_redemptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  promotion_id BIGINT UNSIGNED NOT NULL,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  ride_id BIGINT UNSIGNED NOT NULL,
  discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_redemption_ride_promo (ride_id, promotion_id),
  INDEX idx_redemption_rider (rider_user_id),
  CONSTRAINT fk_pr_promotion FOREIGN KEY (promotion_id) REFERENCES promotions (id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_rider FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rider_loyalty_state (
  rider_user_id BIGINT UNSIGNED PRIMARY KEY,
  paid_trips_count INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rls_rider FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rider_reward_grant (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  promotion_id BIGINT UNSIGNED NOT NULL,
  status ENUM('available', 'applied') NOT NULL DEFAULT 'available',
  applied_ride_id BIGINT UNSIGNED NULL,
  expires_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_grant_rider_status (rider_user_id, status),
  INDEX idx_grant_promo (promotion_id),
  CONSTRAINT fk_rrg_rider FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_rrg_promo FOREIGN KEY (promotion_id) REFERENCES promotions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sos_incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ride_id BIGINT UNSIGNED NOT NULL,
  reporter_rider_user_id BIGINT UNSIGNED NOT NULL,
  reporter_role ENUM('rider', 'driver') NOT NULL,
  latitude DECIMAL(10, 7) NOT NULL,
  longitude DECIMAL(10, 7) NOT NULL,
  accuracy_m DECIMAL(10, 2) NULL,
  message VARCHAR(500) NULL,
  client_request_id CHAR(36) NULL,
  status ENUM('open', 'acknowledged', 'closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME NULL,
  acknowledged_by_admin_id INT UNSIGNED NULL,
  UNIQUE KEY uq_sos_client_request (client_request_id),
  INDEX idx_sos_ride (ride_id),
  INDEX idx_sos_status_created (status, created_at),
  CONSTRAINT fk_sos_ride FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE CASCADE,
  CONSTRAINT fk_sos_reporter FOREIGN KEY (reporter_rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_sos_admin FOREIGN KEY (acknowledged_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ride extensions (ignore errors if columns already exist — run statements individually if needed).
ALTER TABLE rides
  ADD COLUMN driver_rider_user_id BIGINT UNSIGNED NULL AFTER rider_user_id,
  ADD COLUMN estimated_fare_amount DECIMAL(12, 2) NULL AFTER dropoff_address,
  ADD COLUMN promo_discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0 AFTER estimated_fare_amount,
  ADD COLUMN final_fare_amount DECIMAL(12, 2) NULL AFTER promo_discount_amount,
  ADD COLUMN fare_currency CHAR(3) NOT NULL DEFAULT 'NGN' AFTER final_fare_amount,
  ADD COLUMN applied_promotion_id BIGINT UNSIGNED NULL AFTER fare_currency,
  ADD COLUMN promo_code_used VARCHAR(64) NULL AFTER applied_promotion_id,
  ADD COLUMN reward_grant_id BIGINT UNSIGNED NULL AFTER promo_code_used,
  ADD COLUMN payment_status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending' AFTER reward_grant_id,
  ADD COLUMN paid_at DATETIME NULL AFTER payment_status;

ALTER TABLE rides
  ADD CONSTRAINT fk_rides_driver_rider FOREIGN KEY (driver_rider_user_id) REFERENCES rider_users (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_rides_applied_promo FOREIGN KEY (applied_promotion_id) REFERENCES promotions (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_rides_reward_grant FOREIGN KEY (reward_grant_id) REFERENCES rider_reward_grant (id) ON DELETE SET NULL;

ALTER TABLE promotion_redemptions
  ADD CONSTRAINT fk_pr_ride FOREIGN KEY (ride_id) REFERENCES rides (id) ON DELETE CASCADE;

ALTER TABLE rider_reward_grant
  ADD CONSTRAINT fk_rrg_applied_ride FOREIGN KEY (applied_ride_id) REFERENCES rides (id) ON DELETE SET NULL;

-- RBAC
INSERT IGNORE INTO admin_permissions (slug, label, category) VALUES
('sos.view', 'View SOS incidents', 'safety'),
('sos.manage', 'Acknowledge SOS incidents', 'safety'),
('promotions.manage', 'Manage promotions & pricing', 'marketing');

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'system_admin'
  AND p.slug IN ('sos.view', 'sos.manage', 'promotions.manage');
