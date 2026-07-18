# Cutover Redirect Plan

## Product URLs

- Keep Laravel canonical URLs at `/products/{slug}`.
- Accept numeric legacy URLs on the same route and return a `301` to the slug version.
- If a product is unpublished, return `404` instead of redirecting.

## Public pages

- Point the public domain root `/` to Laravel first.
- Move `/products/*`, `/sitemap.xml`, and `/robots.txt` to Laravel in the same release.
- Leave checkout, account, login, register, and admin on the existing React application until their Laravel replacements exist.

## Edge routing

- Reverse proxy `/api/*` to the existing backend until each module is migrated.
- Reverse proxy `/uploads/*` to the legacy asset host or final CDN.
- Add monitoring for `404`, `301`, and response time after cutover.
