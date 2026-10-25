# PocketCat v19.68 — Single-source plan catalogue fixes

- Added canonical plan metadata defaults alongside quotas and feature flags in `system_settings.policy_engine`.
- Reworked policy data caching so a successful save refreshes the in-request cache instead of leaving stale plan values available.
- Added `includes/plan-catalog.php` and one reusable `includes/plan-card.php` used on the homepage, `/pricing/`, and `/billing/`.
- Feature lists now reflect configured quotas and enabled feature flags, including URL, file-sharing, API, analytics, and code-editor capabilities.
- Added no-store response headers to dynamic plan pages to prevent stale HTML from browser/CDN caches.
- Added `GET /api/plans/` and CSRF-protected `POST`/`PUT /api/admin/plans/` endpoints using the same policy document.
- Added a sticky Save Plan Settings button at the top of `/admin/plans/` while retaining the bottom submit action.
- Kept Free enabled for new registrations and retained the existing signup assignment to the `free` plan.
- Documented the canonical storage model and API contract in `docs/PLAN-CATALOG-SINGLE-SOURCE.md`.
- Added regression coverage for saved price synchronization, shared card features, API payloads, and cache invalidation.

Validation: PHP syntax checks and `make check` pass. Live database, Apache rewrite, and CDN behavior still need deployment-level verification.
