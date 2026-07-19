-- Add explicit tier column to products (essential / core / signature).
-- Run this manually only if you are not using `php artisan migrate`
-- (the equivalent Laravel migration lives at
-- database/migrations/2026_07_19_000000_add_tier_to_products_table.php).

ALTER TABLE `products`
  ADD COLUMN `tier` VARCHAR(20) NOT NULL DEFAULT 'core' AFTER `price_thb`;

-- One-time backfill for existing rows, mirroring the old price-based guess
-- so current data doesn't change tier the moment this column appears.
UPDATE `products` SET `tier` = 'signature' WHERE `price_thb` >= 750;
UPDATE `products` SET `tier` = 'essential' WHERE `price_thb` <= 400 AND `price_thb` > 0;
