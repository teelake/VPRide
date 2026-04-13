-- Seed: default system admin + active region JSON (same data as scripts/seed.php).
-- Run in phpMyAdmin AFTER schema_tables_only.sql, on the correct database.
-- Run once. If "Duplicate entry" on email, skip the first INSERT or delete the row first.
--
-- Login after import:
--   Email:    admin@vpride.local
--   Password: Admin@123
-- Change the password from the admin UI when you add that feature, or update password_hash in DB.
--
-- Bcrypt for Admin@123 (PHP password_hash, cost 10):
-- If you change the password, regenerate: php -r "echo password_hash('YourPass', PASSWORD_BCRYPT);"

INSERT INTO admins (email, password_hash, role) VALUES (
  'admin@vpride.local',
  '$2y$10$YcpMZXQroXnD/ejZXa7i2eNXfTcsqNam14dy5zvlEKQouKoZ7gaJ6',
  'system_admin'
);

INSERT INTO region_configs (label, payload, is_active, updated_by_admin_id) VALUES (
  'Default — Modern Canada',
  '{"version":1,"updatedAt":"2026-04-13T00:00:00Z","branding":{"serviceAreaLabel":"Modern Canada"},"localization":{"defaultLocale":"en_CA","supportedLocales":["en_CA","fr_CA"]},"countries":[{"code":"CA","name":"Canada","currencyCode":"CAD","distanceUnit":"km","cities":[{"id":"yyz","name":"Toronto","subdivision":"ON","isActive":true,"center":{"latitude":43.6532,"longitude":-79.3832}},{"id":"yvr","name":"Vancouver","subdivision":"BC","isActive":true,"center":{"latitude":49.2827,"longitude":-123.1207}}]}],"defaults":{"countryCode":"CA","cityId":"yyz"}}',
  1,
  LAST_INSERT_ID()
);
