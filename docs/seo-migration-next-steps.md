# SEO Migration Next Steps

## What is already in place

- Laravel 8 scaffolded in `version-Laravel`
- Server-rendered routes for `/` and `/products/{identifier}`
- Legacy numeric product URLs redirect to the canonical slug path
- Product repository reads from the Laravel `products` table when available
- JSON fallback keeps the storefront renderable before database wiring is complete
- Migration prepared to add `slug`, `meta_title`, `meta_description`, and `og_image`

## Recommended next steps

1. Point `.env` at the legacy MySQL database from `version-react/backend/.env.example`.
2. Run `php artisan migrate` and backfill `products.slug` for existing records.
3. Move the current product list from Express into Laravel seeders or admin CRUD.
4. Add `sitemap.xml`, `robots.txt`, and organization-level structured data.
5. Serve product images from Laravel public storage or the final CDN instead of the temporary Express asset base URL.
6. Replace the remaining React public routes with Laravel pages, then leave checkout/account/admin on React until later.
