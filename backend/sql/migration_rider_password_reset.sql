-- Rider app "forgot password" tokens (run after rider_users exists).
-- Email contains a link to /rider/reset-password?token=… (see public/rider/reset_password.php).

CREATE TABLE IF NOT EXISTS rider_password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  UNIQUE KEY uq_rpr_token_hash (token_hash),
  INDEX idx_rpr_rider_pending (rider_user_id, used_at),
  INDEX idx_rpr_expires (expires_at),
  CONSTRAINT fk_rpr_rider FOREIGN KEY (rider_user_id) REFERENCES rider_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
