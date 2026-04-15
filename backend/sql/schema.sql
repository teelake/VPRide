-- VPRide backend: admins + region config (MySQL 8+ recommended for JSON type)
CREATE DATABASE IF NOT EXISTS vpride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vpride;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('system_admin', 'dispatcher', 'support') NOT NULL DEFAULT 'support',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS region_configs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(128) NOT NULL DEFAULT '',
  payload JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_admin_id INT UNSIGNED NULL,
  CONSTRAINT fk_region_updated_by FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL,
  INDEX idx_region_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_sub VARCHAR(255) NOT NULL,
  email VARCHAR(320) NOT NULL,
  display_name VARCHAR(255) NULL,
  photo_url VARCHAR(512) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rider_google_sub (google_sub),
  INDEX idx_rider_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  CONSTRAINT fk_rider_sess_user FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE,
  UNIQUE KEY uq_rider_token_hash (token_hash),
  INDEX idx_rider_sess_expires (expires_at),
  INDEX idx_rider_sess_user (rider_user_id)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;
