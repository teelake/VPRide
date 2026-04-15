-- Rider app auth (run once). Requires MySQL 8+ for JSON elsewhere; these tables do not use JSON.
-- In phpMyAdmin: select your database first (e.g. u232647434_vpride). Do not rely on USE vpride on shared hosting.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
