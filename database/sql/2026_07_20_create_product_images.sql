CREATE TABLE `product_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(512) NOT NULL,
  `alt` VARCHAR(512) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_sort_idx` (`product_id`, `sort_order`),
  CONSTRAINT `product_images_product_id_foreign`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `product_images` (`product_id`, `image_path`, `alt`, `sort_order`, `is_primary`, `created_at`, `updated_at`)
SELECT
  `id`,
  `image`,
  `alt`,
  0,
  1,
  NOW(),
  NOW()
FROM `products`
WHERE `image` IS NOT NULL
  AND `image` <> '';
