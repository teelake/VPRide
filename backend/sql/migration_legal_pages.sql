-- Rider-facing legal documents (Terms of Use, Privacy Policy). Edited in admin with Quill; served to the mobile app via GET /api/v1/legal-pages/{slug}.

CREATE TABLE IF NOT EXISTS rider_legal_pages (
  slug VARCHAR(64) NOT NULL PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_admin_id INT UNSIGNED NULL,
  CONSTRAINT fk_rider_legal_pages_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rider_legal_pages (slug, title, body_html, updated_by_admin_id)
VALUES
  (
    'terms_of_use',
    'Terms of Use',
    '<p>Terms of Use content has not been published yet. Please check back soon or contact support.</p>',
    NULL
  ),
  (
    'privacy_policy',
    'Privacy Policy',
    '<p>Privacy Policy content has not been published yet. Please check back soon or contact support.</p>',
    NULL
  )
ON DUPLICATE KEY UPDATE slug = slug;
