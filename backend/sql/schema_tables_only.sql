-- Import AFTER you create the database in cPanel/Plesk and open it in phpMyAdmin.
-- Do not run CREATE DATABASE here — shared hosts usually forbid it or assign a prefixed name.
-- Charset: utf8mb4_unicode_ci (match your DB default if the panel sets it already).

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
  display_name VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admins_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles (id),
  INDEX idx_admins_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  UNIQUE KEY uq_apr_token_hash (token_hash),
  INDEX idx_apr_admin_pending (admin_id, used_at),
  INDEX idx_apr_expires (expires_at),
  CONSTRAINT fk_apr_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
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
