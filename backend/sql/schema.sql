-- VP Ride backend: RBAC admins + region config (MySQL 8+ recommended for JSON type)
CREATE DATABASE IF NOT EXISTS vpride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vpride;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  label VARCHAR(128) NOT NULL,
  category VARCHAR(64) NOT NULL DEFAULT 'general',
  UNIQUE KEY uq_admin_perm_slug (slug),
  INDEX idx_admin_perm_cat (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_roles (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  label VARCHAR(128) NOT NULL,
  is_superuser TINYINT(1) NOT NULL DEFAULT 0,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_role_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id SMALLINT UNSIGNED NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_arp_perm FOREIGN KEY (permission_id) REFERENCES admin_permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_permissions (slug, label, category) VALUES
('dashboard.view', 'View dashboard', 'overview'),
('regions.view', 'View regions', 'regions'),
('regions.manage', 'Manage regions & activation', 'regions'),
('rides.view', 'View rides', 'rides'),
('riders.view', 'View riders', 'riders'),
('team.view', 'View team', 'team'),
('team.manage', 'Manage team accounts', 'team'),
('settings.manage', 'App settings & feature flags', 'platform'),
('reports.view', 'View reports', 'reports'),
('reports.export', 'Export CSV reports', 'reports'),
('rbac.manage', 'Roles & permissions', 'platform');

INSERT IGNORE INTO admin_roles (slug, label, is_superuser, is_system) VALUES
('system_admin', 'System administrator', 1, 1),
('dispatcher', 'Dispatcher', 0, 1),
('support', 'Support', 0, 1);

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'dispatcher'
  AND p.slug IN (
    'dashboard.view', 'regions.view', 'rides.view', 'riders.view', 'team.view',
    'reports.view', 'reports.export'
  );

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'support'
  AND p.slug IN (
    'dashboard.view', 'rides.view', 'riders.view', 'reports.view', 'reports.export'
  );

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admins_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles (id),
  INDEX idx_admins_role (role_id)
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

CREATE TABLE IF NOT EXISTS app_public_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  payload JSON NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_admin_id INT UNSIGNED NULL,
  CONSTRAINT fk_app_public_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB;
