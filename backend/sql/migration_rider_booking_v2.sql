-- Rider booking v2: fixed distance pricing, scheduled pickup, round-trip legs, ratings.
-- Run on your VP Ride DB after migration_sos_promos_loyalty.sql (platform_promo_settings + rides exist).

ALTER TABLE platform_promo_settings
  ADD COLUMN pricing_base_fare DECIMAL(12, 2) NOT NULL DEFAULT 500.00 AFTER default_ride_estimate,
  ADD COLUMN pricing_per_km DECIMAL(12, 4) NOT NULL DEFAULT 350.0000 AFTER pricing_base_fare,
  ADD COLUMN pricing_minimum_fare DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER pricing_per_km,
  ADD COLUMN advance_booking_max_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER loyalty_reward_promotion_id;

ALTER TABLE rides
  ADD COLUMN scheduled_pickup_at DATETIME NULL AFTER updated_at,
  ADD COLUMN trip_leg ENUM('single', 'outbound', 'return') NOT NULL DEFAULT 'single' AFTER scheduled_pickup_at,
  ADD COLUMN companion_ride_id BIGINT UNSIGNED NULL AFTER trip_leg,
  ADD COLUMN distance_km DECIMAL(10, 5) NULL AFTER companion_ride_id,
  ADD COLUMN rating_stars TINYINT UNSIGNED NULL AFTER distance_km,
  ADD COLUMN feedback_text VARCHAR(2000) NULL AFTER rating_stars,
  ADD COLUMN rated_at DATETIME NULL AFTER feedback_text;

ALTER TABLE rides
  ADD CONSTRAINT fk_rides_companion FOREIGN KEY (companion_ride_id) REFERENCES rides (id) ON DELETE SET NULL;
