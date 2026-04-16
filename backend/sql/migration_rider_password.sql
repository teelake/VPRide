-- Email/password for rider app (optional; Google still supported).
-- Run after migration_rider_auth.sql. Requires unique emails for login.

ALTER TABLE rider_users
  MODIFY google_sub VARCHAR(255) NULL,
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER google_sub;

-- Enforce one account per email (may fail if duplicate emails exist — clean data first).
ALTER TABLE rider_users DROP INDEX idx_rider_email;
ALTER TABLE rider_users ADD UNIQUE KEY uq_rider_users_email (email);
