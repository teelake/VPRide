-- Fleet-provisioned logins (see RiderAuthService::createPasswordUserWithGeneratedPassword):
-- when 1, mobile app shows driver shell only (no passenger / ride booking UI).
ALTER TABLE rider_users
  ADD COLUMN driver_account_only TINYINT(1) NOT NULL DEFAULT 0;

-- Optional backfill for drivers provisioned before this column existed (adjust to your schema):
-- UPDATE rider_users u
-- INNER JOIN fleet_drivers d ON d.rider_user_id = u.id AND d.status = 'active'
-- SET u.driver_account_only = 1
-- WHERE u.driver_account_only = 0;
