-- Fleet vehicles and console driver records (admin-managed).
-- Run on your VP Ride database after backup.
-- personal  = driver-owned car (owner-operators)
-- company   = brand / fleet vehicle assigned to company drivers

CREATE TABLE IF NOT EXISTS fleet_vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ownership ENUM('personal', 'company') NOT NULL,
  company_fleet_label VARCHAR(128) NULL,
  plate_number VARCHAR(32) NOT NULL,
  make VARCHAR(64) NULL,
  model VARCHAR(64) NULL,
  color VARCHAR(48) NULL,
  year SMALLINT UNSIGNED NULL,
  seat_count TINYINT UNSIGNED NULL,
  vin VARCHAR(32) NULL,
  status ENUM('active', 'maintenance', 'retired') NOT NULL DEFAULT 'active',
  notes VARCHAR(512) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fleet_vehicles_ownership (ownership),
  INDEX idx_fleet_vehicles_status (status),
  INDEX idx_fleet_vehicles_plate (plate_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fleet_drivers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(32) NULL,
  email VARCHAR(255) NULL,
  driver_kind ENUM('owner_operator', 'company_driver') NOT NULL,
  fleet_vehicle_id BIGINT UNSIGNED NULL,
  license_number VARCHAR(64) NULL,
  status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
  notes VARCHAR(512) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fleet_drivers_vehicle FOREIGN KEY (fleet_vehicle_id) REFERENCES fleet_vehicles (id) ON DELETE SET NULL,
  INDEX idx_fleet_drivers_kind (driver_kind),
  INDEX idx_fleet_drivers_status (status),
  INDEX idx_fleet_drivers_vehicle (fleet_vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBAC: allow dispatchers to manage fleet (system_admin is superuser — already all access)
INSERT IGNORE INTO admin_permissions (slug, label, category) VALUES
('fleet.manage', 'Manage fleet vehicles & driver records', 'fleet');

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM admin_roles r
CROSS JOIN admin_permissions p
WHERE r.slug = 'dispatcher'
  AND p.slug = 'fleet.manage';
