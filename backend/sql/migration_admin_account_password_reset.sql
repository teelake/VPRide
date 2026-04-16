-- Admin profile + password reset (run after backup).
-- Adds optional display name, and token table for "forgot password" emails.

ALTER TABLE admins
  ADD COLUMN display_name VARCHAR(255) NULL AFTER email;

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
