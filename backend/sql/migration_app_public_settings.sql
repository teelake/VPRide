-- Single-row public app settings (mobile reads via GET /api/v1/config/public).
-- Server-side JWT verification still prefers GOOGLE_OAUTH_CLIENT_ID in .env when set.

USE vpride;

CREATE TABLE IF NOT EXISTS app_public_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  payload JSON NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_admin_id INT UNSIGNED NULL,
  CONSTRAINT fk_app_public_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO app_public_settings (id, payload) VALUES (
  1,
  JSON_OBJECT(
    'googleWebClientId', '',
    'mapsApiKey', '',
    'minimumAppVersion', '1.0.0'
  )
) ON DUPLICATE KEY UPDATE id = VALUES(id);
