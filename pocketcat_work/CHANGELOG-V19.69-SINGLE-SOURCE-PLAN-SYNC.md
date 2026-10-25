# PocketCat V19.69 — Single-Source Plan Synchronization

- **RELEASED:** 2026-10-20
- **SCOPE:** V19.68 → V19.69
- **STATUS:** Implemented; automated source checks are run before packaging. Live production database/browser verification remains environment-dependent.

## Changes

- **Plan metadata and quota persistence** → Admin plan metadata and feature allocations could appear different across page contexts or rely on an unverified save interaction → kept `system_settings.policy_engine` as the canonical, atomic JSON policy document, added a policy update timestamp, and ensured the shared catalogue reads current policy data through `includes/policy.php` and `includes/plan-catalog.php`.
- **Admin save workflow** → The save interaction did not provide clear asynchronous success/error feedback → added `assets/js/admin-plan-settings.js` to serialize all three plan metadata objects and every boolean/numeric/string allocation, send them to `POST /api/admin/plans`, disable duplicate submissions, display accessible toast feedback, and reload from persisted state after success. Native PHP form submission remains available if JavaScript is unavailable.
- **Admin API validation** → Partial or malformed API payloads could leave an ambiguous result → `api/admin/plans.php` now requires metadata and complete allocation maps for Free, Basic, and Premium; validates the full payload; persists through `policy_save`; and returns a structured response containing the refreshed catalogue.
- **Public catalogue API** → Public clients needed an explicit stable model → `GET /api/plans` now returns the canonical plan catalogue with `schema_version`, `updated_at`, explicit `slug`, `billing_interval`, active `status`, human-readable feature lists, and raw allocation values, with no-cache headers. Added `PLAN-CATALOG-API.md` to document the model and request/response contracts.
- **Pricing, billing, and homepage** → Public and authenticated cards could show different subsets or layouts → all three surfaces use `includes/plan-card.php` and `plan_card_features()` from the same saved policy catalogue; the feature renderer also humanizes newly configured boolean, numeric, and string capabilities not yet in the label map. Shared responsive CSS ensures every generated feature row remains visible and readable without clipping.
- **Quota and feature descriptions** → File expiry had a duplicate unreachable condition → corrected the feature label branch while preserving existing zero/unlimited policy semantics.
- **Free signup lifecycle** → The Free CTA did not explicitly carry its selected tier → the Free CTA now passes `plan=free`; `auth/signup.php` validates the requested tier, refuses signup if Free is disabled, and writes the canonical Free tier to the new user record so runtime quotas resolve from the live policy matrix. Paid plans cannot be selected through signup to bypass checkout.
- **Responsive UI and feedback** → Long feature lists and mobile save feedback needed predictable behavior → added responsive card grids, visible overflow-safe feature lists, accessible save toasts, reduced-motion handling, and mobile breakpoints in `assets/css/app.css`.
- **Asset cache invalidation** → Browsers could retain old pricing CSS or JavaScript → bumped app and responsive CSS cache versions and versioned the new admin plan script.
- **Version Details** → Release metadata still pointed at the prior release → updated `VERSION` to `19.69`, and `admin/version-details.php` to display the V19.68 → V19.69 scope, release date, summary, and this changelog.

## Database / Model

No new plan table is introduced because the existing policy engine is already consumed by quota enforcement and feature authorization. The canonical stored model remains the `system_settings` row with `setting_key = 'policy_engine'`, containing:

- `plan_meta`: `free`, `basic`, and `premium` objects with `name`, decimal-string `price`, `period` (`month`, `year`, `one-time`), `description`, and boolean `enabled`.
- `plans`: one object per canonical tier, with every quota as a non-negative integer, feature switches as booleans, and enumerated options such as `url_redirect_mode` as strings.
- `system_settings.policy_engine_updated_at`: ISO-8601 UTC timestamp updated on every policy save.

`users.plan` continues to store the canonical tier key. Runtime entitlements are resolved through `policy_effective()` and the current policy matrix rather than copied into per-user quota columns.

## API Contract

- `GET /api/plans`: public read-only catalogue; returns `{ success, schema_version, updated_at, plans }`.
- `POST /api/admin/plans` and `PUT /api/admin/plans`: super-admin-only write; requires a same-origin CSRF token in `X-CSRF-Token`; accepts complete `plan_meta` and `plans` maps; returns `{ success, message, updated_at, catalog }` or a JSON error with an appropriate HTTP status.
- The public pricing, homepage, and authenticated billing pages read the same catalogue model directly on the server. The public API serializes that exact same model for any future client-side consumers.

## Operational notes

- Automated verification includes PHP syntax linting, existing policy/quota/catalog/webhook tests, and the plan-catalog UI test. ZIP integrity is checked after packaging.
- Save operations use the existing database connection and policy persistence function; no migration is needed for the canonical JSON model. The `policy_engine` setting is written as one database row, preventing a partial tier-by-tier catalogue update.
- CSRF validation, super-admin permission checks, strict plan keys, full allocation presence, non-negative quota validation, valid billing periods, valid prices, and redirect-mode validation remain enforced.
- Free signup assigns `users.plan = 'free'`; the current saved Free quotas and feature flags are evaluated at runtime. Paid signup is not enabled because checkout and payment confirmation are not configured.
- Static tests cannot validate production database permissions, Apache rewrite configuration, browser rendering, or real customer session behavior. Deploy to staging first and perform the smoke-test list below before production rollout.

## Staging Smoke Tests

1. Change Basic price and Premium price and adjust one numeric quota plus one boolean feature; save and verify success feedback.
2. Reload `/admin/plans/`; verify all saved values remain.
3. Open `/api/plans` and verify prices, quota values, and feature arrays match the saved values.
4. Compare `/pricing/`, the homepage plan section, and `/billing/`; verify names, prices, descriptions, and complete feature lists match. Test desktop and narrow mobile widths.
5. Sign up using the Free CTA; verify the user record is assigned `free` and quota checks use the current Free policy.
6. Disable a paid tier and verify it disappears from new selection; verify Free cannot be disabled.
7. Submit malformed API payloads and requests without admin session or CSRF token; verify rejection without policy mutation.
