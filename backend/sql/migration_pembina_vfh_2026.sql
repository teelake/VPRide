-- Pembina Valley: 10 km outskirts buffer, Schedule C–style pricing toggles, ride→vehicle, driver mode.
-- Run on your vpride DB after platform_promo_settings + rides + fleet_vehicles exist.
-- Re-run safe: each step checks information_schema.

-- platform_promo_settings: add columns one-by-one
SET @t := 'platform_promo_settings';

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'service_buffer_km');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN service_buffer_km DECIMAL(6,2) NOT NULL DEFAULT 10.00', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'service_licensed_radius_km');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN service_licensed_radius_km DECIMAL(6,2) NOT NULL DEFAULT 15.00', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'enforce_service_area');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN enforce_service_area TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'pricing_mode');
SET @q := IF(@c = 0, "ALTER TABLE platform_promo_settings ADD COLUMN pricing_mode VARCHAR(32) NOT NULL DEFAULT 'distance' AFTER enforce_service_area", 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'use_flat_town_pricing');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN use_flat_town_pricing TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'flat_town_fare');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN flat_town_fare DECIMAL(12,2) NULL DEFAULT 10.00', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'flat_town_max_distance_km');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN flat_town_max_distance_km DECIMAL(6,2) NULL DEFAULT 8.00', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'meter_base_day');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN meter_base_day DECIMAL(10,2) NULL DEFAULT 4.75', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'meter_base_night');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN meter_base_night DECIMAL(10,2) NULL DEFAULT 6.65', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'meter_per_100m');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN meter_per_100m DECIMAL(10,3) NULL DEFAULT 0.211', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'meter_per_15s_wait');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN meter_per_15s_wait DECIMAL(10,2) NULL DEFAULT 0.15', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'meter_night_start_hour');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN meter_night_start_hour TINYINT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'meter_night_end_hour');
SET @q := IF(@c = 0, 'ALTER TABLE platform_promo_settings ADD COLUMN meter_night_end_hour TINYINT UNSIGNED NOT NULL DEFAULT 6', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- fleet_drivers: vehicle assignment mode
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fleet_drivers' AND COLUMN_NAME = 'vehicle_assignment_mode');
SET @q := IF(@c = 0, "ALTER TABLE fleet_drivers ADD COLUMN vehicle_assignment_mode ENUM('fixed','flexible') NOT NULL DEFAULT 'fixed' AFTER status", 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- rides: optional fleet vehicle for trip accountability
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rides' AND COLUMN_NAME = 'fleet_vehicle_id');
SET @q := IF(@c = 0, 'ALTER TABLE rides ADD COLUMN fleet_vehicle_id BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rides' AND INDEX_NAME = 'idx_rides_fleet_vehicle');
SET @q := IF(@c = 0, 'CREATE INDEX idx_rides_fleet_vehicle ON rides (fleet_vehicle_id)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
