-- DB-backed RBAC for VP Ride admin console.
-- Run on your live DB after backup. Requires existing `admins` with ENUM `role`.
-- Then: ALTER drops enum column — verify UPDATE counts before DROP.

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

-- Link existing admins to roles (ENUM value must match role slug).
ALTER TABLE admins ADD COLUMN role_id SMALLINT UNSIGNED NULL AFTER password_hash;

UPDATE admins a
INNER JOIN admin_roles r ON r.slug = a.role
SET a.role_id = r.id;

UPDATE admins a
INNER JOIN admin_roles r ON r.slug = 'support'
SET a.role_id = r.id
WHERE a.role_id IS NULL;

ALTER TABLE admins
  MODIFY role_id SMALLINT UNSIGNED NOT NULL,
  ADD CONSTRAINT fk_admins_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles (id),
  DROP COLUMN role;
