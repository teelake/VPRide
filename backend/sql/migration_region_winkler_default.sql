-- Upgrade legacy region config: "Modern Canada" (typo for Morden) + Toronto (yyz) default
-- to Pembina Valley — Winkler, MB (active), with Morden / Altona / Carman present but inactive
-- (future licensing in Pembina Valley).
--
-- Safe to run multiple times: only updates rows that still look like the old default.
-- Run in phpMyAdmin or mysql CLI on the vpride database after backup.
--
-- Does NOT re-run admin seed: only UPDATE existing region_configs.
--
-- Match conditions (any):
--   • branding.serviceAreaLabel = 'Modern Canada'
--   • internal label contains 'Modern Canada' (e.g. 'Default — Modern Canada')
--
-- If you still use defaults.cityId = 'yyz' but already fixed the label, run a manual UPDATE
-- or extend this script’s WHERE clause for your case.

-- Single-line JSON for compatibility with stricter MySQL / phpMyAdmin clients.
SET @vpride_winkler_payload = '{"version":1,"updatedAt":"2026-04-24T00:00:00Z","branding":{"serviceAreaLabel":"Winkler, MB"},"localization":{"defaultLocale":"en_CA","supportedLocales":["en_CA","fr_CA"]},"countries":[{"code":"CA","name":"Canada","currencyCode":"CAD","distanceUnit":"km","cities":[{"id":"winkler","name":"Winkler","subdivision":"MB","isActive":true,"center":{"latitude":49.1817,"longitude":-97.9411}},{"id":"morden","name":"Morden","subdivision":"MB","isActive":false,"center":{"latitude":49.1919,"longitude":-98.102}},{"id":"altona","name":"Altona","subdivision":"MB","isActive":false,"center":{"latitude":49.1047,"longitude":-97.1655}},{"id":"carman","name":"Carman","subdivision":"MB","isActive":false,"center":{"latitude":49.4991,"longitude":-98.0016}}]}],"defaults":{"countryCode":"CA","cityId":"winkler"}}';
UPDATE region_configs
SET
  label = 'Default — Winkler, MB',
  payload = CAST(@vpride_winkler_payload AS JSON)
WHERE
  JSON_TYPE(payload) = 'OBJECT'
  AND (
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.branding.serviceAreaLabel')) = 'Modern Canada'
    OR label LIKE '%Modern Canada%'
  );

-- Optional: if no row matched, check:
--   SELECT id, label, is_active, JSON_UNQUOTE(JSON_EXTRACT(payload, '$.branding.serviceAreaLabel')) AS area
--   FROM region_configs;
