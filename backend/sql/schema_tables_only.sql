-- Import AFTER you create the database in cPanel/Plesk and open it in phpMyAdmin.
-- Do not run CREATE DATABASE here — shared hosts usually forbid it or assign a prefixed name.
-- Charset: utf8mb4_unicode_ci (match your DB default if the panel sets it already).

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('system_admin', 'dispatcher', 'support') NOT NULL DEFAULT 'support',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
