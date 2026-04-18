-- Fleet-provisioned riders may be required to change password on first app login.
-- Run once on your VP Ride database.

ALTER TABLE rider_users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = require in-app password change before full access'
  AFTER photo_url;
