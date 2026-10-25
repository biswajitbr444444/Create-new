# PocketCat v19.68 — Pricing, Billing, Control Plane, and Mobile Navigation Fixes

## Changes
- Guarded the generic short-link rewrite so existing files/directories are not treated as short-link slugs; this allows `/pricing/` to reach `pricing/index.php`.
- Replaced hardcoded homepage plan cards with policy-backed plan metadata and quota summaries.
- Rebuilt `/billing/` plan cards to read names, prices, periods, descriptions, and feature allowances from `policy_data()`; accurately states that checkout is not configured rather than implying a payment was taken.
- Added a dedicated, sticky `Save tier matrix` action immediately after the Dynamic 3-tier matrix in the Control Plane form. It submits the same CSRF-protected policy form.
- Changed the authenticated top-bar `View Site` link to open the public homepage in the same tab.
- Kept the profile avatar and sign-out link visible at mobile breakpoints and added small-screen spacing adjustments.
- Bumped stylesheet query versions to invalidate stale CSS caches.

## Verification performed
- `php -l` passed for edited PHP files.
- `make check` passed policy, quota, plan-catalog, and webhook-target smoke tests and recursively linted PHP files.
- Archive integrity should be checked after packaging.

## Not verified in this environment
- Live Apache rewrite behavior and hosting-specific configuration.
- Browser-based mobile visual regression.
- Live database synchronization against an installed instance.
- Payment checkout: this build has no configured automated payment provider/checkout flow.
