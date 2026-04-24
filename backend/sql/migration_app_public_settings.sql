-- Single-row public app settings (mobile reads via GET /api/v1/config/public).
-- Server-side JWT verification still prefers GOOGLE_OAUTH_CLIENT_ID in .env when set.
-- Payload key googleWebClientId stores the Google Sign-In *server* client ID (ID token audience),
-- not a "website" app; API also exposes it as googleServerClientId.

CREATE TABLE IF NOT EXISTS app_public_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  payload JSON NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_admin_id INT UNSIGNED NULL,
  CONSTRAINT fk_app_public_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default payload for new installs (MySQL 8+ nested JSON_OBJECT).
INSERT INTO app_public_settings (id, payload) VALUES (
  1,
  JSON_OBJECT(
    'googleWebClientId', '',
    'mapsApiKey', '',
    'minimumAppVersion', '1.0.0',
    'welcome', JSON_OBJECT(
      'backgroundImageUrl', '',
      'overlayColor', '#F0F0F0',
      'overlayOpacity', 0.78,
      'brandWordmark', 'VP Ride',
      'headline', 'Move with intention',
      'subhead', 'Book a ride in a few taps, or open the map to choose your pickup. We are built for {{region}}.',
      'featureLeftTitle', 'Elite Safety',
      'featureRightTitle', 'On Demand',
      'footerTagline', 'NAVIGATE THE CITY',
      'showFeatureRow', true,
      'showPagerDots', true,
      'ctaRegister', 'Create account',
      'ctaEmailLogin', 'Sign in',
      'ctaGoogle', 'Continue with Google'
    ),
    'features', JSON_OBJECT(
      'rideBookingEnabled', true,
      'promoBannerEnabled', false,
      'maintenanceMode', false,
      'maintenanceMessage', '',
      'helpCenterUrl', '',
      'requireSignInForHome', true
    )
  )
) ON DUPLICATE KEY UPDATE id = VALUES(id);
