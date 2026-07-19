-- Create home_content: a single-row table holding the editable text and
-- image overrides for the storefront home page (hero, featured core,
-- signature, manifesto sections).
-- Run this manually only if you are not using `php artisan migrate`
-- (the equivalent Laravel migration lives at
-- database/migrations/2026_07_19_000001_create_home_content_table.php).

CREATE TABLE `home_content` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Hero
  `hero_eyebrow` VARCHAR(255) NOT NULL DEFAULT '',
  `hero_subtitle` TEXT NULL,
  `hero_cta_primary_label` VARCHAR(100) NOT NULL DEFAULT '',
  `hero_cta_secondary_label` VARCHAR(100) NOT NULL DEFAULT '',
  `hero_image` VARCHAR(512) NULL,

  -- Featured core
  `feature_eyebrow` VARCHAR(255) NOT NULL DEFAULT '',
  `feature_title` VARCHAR(255) NOT NULL DEFAULT '',
  `feature_note` TEXT NULL,
  `feature_kicker` VARCHAR(255) NOT NULL DEFAULT '',
  `feature_heading_line1` VARCHAR(255) NOT NULL DEFAULT '',
  `feature_heading_line2` VARCHAR(255) NOT NULL DEFAULT '',
  `feature_copy` TEXT NULL,
  `feature_cta_label` VARCHAR(100) NOT NULL DEFAULT '',
  `feature_image` VARCHAR(512) NULL,

  -- Signature
  `signature_kicker` VARCHAR(255) NOT NULL DEFAULT '',
  `signature_heading_line1` VARCHAR(255) NOT NULL DEFAULT '',
  `signature_heading_line2` VARCHAR(255) NOT NULL DEFAULT '',
  `signature_limited_line1` VARCHAR(255) NOT NULL DEFAULT '',
  `signature_limited_line2` VARCHAR(255) NOT NULL DEFAULT '',
  `signature_limited_line3` VARCHAR(255) NOT NULL DEFAULT '',
  `signature_copy` TEXT NULL,
  `signature_cta_label` VARCHAR(100) NOT NULL DEFAULT '',
  `signature_image` VARCHAR(512) NULL,

  -- Manifesto
  `manifesto_eyebrow` VARCHAR(255) NOT NULL DEFAULT '',
  `manifesto_line1` VARCHAR(255) NOT NULL DEFAULT '',
  `manifesto_line2_prefix` VARCHAR(255) NOT NULL DEFAULT '',
  `manifesto_highlight` VARCHAR(255) NOT NULL DEFAULT '',

  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single content row, seeded with the text currently hardcoded in
-- resources/views/storefront/home.blade.php so nothing changes visually
-- until an admin edits it.
INSERT INTO `home_content` (
  `hero_eyebrow`, `hero_subtitle`, `hero_cta_primary_label`, `hero_cta_secondary_label`, `hero_image`,
  `feature_eyebrow`, `feature_title`, `feature_note`, `feature_kicker`, `feature_heading_line1`, `feature_heading_line2`, `feature_copy`, `feature_cta_label`, `feature_image`,
  `signature_kicker`, `signature_heading_line1`, `signature_heading_line2`, `signature_limited_line1`, `signature_limited_line2`, `signature_limited_line3`, `signature_copy`, `signature_cta_label`, `signature_image`,
  `manifesto_eyebrow`, `manifesto_line1`, `manifesto_line2_prefix`, `manifesto_highlight`,
  `created_at`, `updated_at`
) VALUES (
  'NEW COLLECTION / 3 LEVELS OF EXPRESSION',
  'Dark luxury streetwear built in three levels — from everyday essentials, to the core identity of the brand, to limited signature pieces made to be collected.',
  'Shop the Collection', 'Explore Signature', NULL,

  '02 / CORE', 'FEATURED CORE',
  'Core is where NEBVSIN speaks first — every graphic starts as a feeling before it becomes a t-shirt.',
  '02 / CORE COLLECTION', 'SHADOW', 'IN MY TEETH',
  'A daily statement piece built around visual tension and inner conflict. Clean surface, aggressive detail — the quietest piece from far away, the loudest up close.',
  'Discover the Piece', NULL,

  '03 / SIGNATURE', 'SPLIT', 'MIND',
  'LIMITED EDITION', '60 PIECES WORLDWIDE', 'NO RESTOCK',
  'Signature exists in another world from Essential and Core — red accent, edition numbering, and a collectible experience built to be kept, not just worn.',
  'View Signature Piece', NULL,

  'NEBVSIN MANIFESTO', "WE DON'T SELL CLOTHES.", 'WE SELL ', 'CONFLICT.',

  NOW(), NOW()
);
